<?php
namespace App\Jobs;
use App\Models\{Document,ModelProfile};
use App\Services\ModelProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\{DB,Storage};
use Smalot\PdfParser\Parser;
use Throwable;
class IngestDocument implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public int $tries=3; public int $timeout=180;
 public function __construct(public int $documentId){}
 private function docxText(string $path): string {
  $zip=new \ZipArchive();
  if($zip->open($path)!==true)throw new \RuntimeException('Invalid DOCX container');
  $xml=$zip->getFromName('word/document.xml');$zip->close();
  if(!is_string($xml))throw new \RuntimeException('DOCX document body is missing');
  return html_entity_decode(strip_tags(str_replace(['</w:p>','</w:tr>'],' ',$xml)),ENT_QUOTES|ENT_XML1,'UTF-8');
 }
 public function handle(ModelProvider $provider):void {
  $d=Document::findOrFail($this->documentId);$d->update(['status'=>'processing','error_message'=>null]);$path=null;
  try {
   $stream=Storage::disk($d->disk)->readStream($d->path);
   if(!is_resource($stream))throw new \RuntimeException('Document could not be read');
   $path=tempnam(sys_get_temp_dir(),'abe-');$target=fopen($path,'wb');
   if(!$target){fclose($stream);throw new \RuntimeException('Temporary file could not be created');}
   stream_copy_to_stream($stream,$target);fclose($target);fclose($stream);
   $ext=strtolower(pathinfo($d->path,PATHINFO_EXTENSION));
   $text=match($ext){'pdf'=>(new Parser())->parseFile($path)->getText(),'docx'=>$this->docxText($path),default=>file_get_contents($path)};
   $text=trim(preg_replace('/\s+/u',' ',$text??''));if($text==='')throw new \RuntimeException('No text extracted');
   $profile=ModelProfile::where('organization_id',$d->organization_id)->where('is_active',true)->firstOrFail();
   $expected=(int)$profile->embedding_dimensions;if($expected!==1536)throw new \RuntimeException('The current vector index supports exactly 1536 dimensions.');
   $chunks=mb_str_split($text,1200);$indexed=[];
   foreach(array_chunk($chunks,16) as $batchNumber=>$batch){
    $vectors=$provider->embed($batch,$profile,(int)$d->created_by);
    if(count($vectors)!==count($batch))throw new \RuntimeException('Embedding batch incomplete');
    foreach($batch as $i=>$chunk){
     $vec=$vectors[$i]??null;if(!is_array($vec)||count($vec)!==$expected)throw new \RuntimeException('Embedding dimension does not match the configured vector index.');
     $encoded='['.implode(',',array_map(fn($x)=>(string)(float)$x,$vec)).']';
     $indexed[]=[$d->organization_id,$d->id,$d->created_by,$d->access_scope,$chunk,'characters '.(($batchNumber*16+$i)*1200).'-'.(($batchNumber*16+$i+1)*1200),$encoded];
    }
   }
   DB::transaction(function()use($d,$indexed){$d->chunks()->delete();foreach($indexed as $row)DB::insert('INSERT INTO document_chunks (organization_id,document_id,owner_user_id,access_scope,content,source_locator,embedding,created_at,updated_at) VALUES (?,?,?,?,?,?,?::vector,now(),now())',$row);$d->update(['status'=>'indexed','embedding_model'=>$profile->embedding_model]);});
   @unlink($path);
  }catch(Throwable $e){
   if($path&&is_file($path))@unlink($path);
   $d->update(['status'=>'failed','error_message'=>mb_substr($e->getMessage(),0,500)]);throw $e;
  }
 }
}