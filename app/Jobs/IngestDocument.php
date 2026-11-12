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
 public int $tries=3;public int $timeout=180;
 public function __construct(public int $documentId){}
 private function docxText(string $path): string {
  $zip=new \ZipArchive();
  if($zip->open($path)!==true) throw new \RuntimeException('Invalid DOCX container');
  $xml=$zip->getFromName('word/document.xml');$zip->close();
  if(!is_string($xml)) throw new \RuntimeException('DOCX document body is missing');
  return html_entity_decode(strip_tags(str_replace(['</w:p>','</w:tr>'],' ', $xml)),ENT_QUOTES|ENT_XML1,'UTF-8');
 }
 public function handle(ModelProvider $provider):void{
  $d=Document::findOrFail($this->documentId);$d->update(['status'=>'processing','error_message'=>null]);
  try{
   $path=Storage::disk($d->disk)->path($d->path);$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
   $text=match($ext){'pdf'=>(new Parser())->parseFile($path)->getText(),'docx'=>$this->docxText($path),default=>file_get_contents($path)};
   $text=trim(preg_replace('/\\s+/u',' ',$text??''));if($text==='')throw new \RuntimeException('No text extracted');
   $profile=ModelProfile::where('organization_id',$d->organization_id)->where('is_active',true)->firstOrFail();
   $chunks=mb_str_split($text,1200);
   foreach(array_chunk($chunks,16) as $batchNumber=>$batch){$vectors=$provider->embed($batch,$profile);foreach($batch as $i=>$chunk){
    $vec=$vectors[$i]??null;if(!$vec)throw new \RuntimeException('Embedding batch incomplete');
    $encoded='['.implode(',',array_map(fn($x)=>(string)(float)$x,$vec)).']';
    DB::insert('INSERT INTO document_chunks (organization_id,document_id,owner_user_id,access_scope,content,source_locator,embedding,created_at,updated_at) VALUES (?,?,?,?,?,?,?::vector,now(),now())',[$d->organization_id,$d->id,$d->created_by,$d->access_scope,$chunk,'characters '.(($batchNumber*16+$i)*1200).'-'.(($batchNumber*16+$i+1)*1200),$encoded]);
   }}
   $d->update(['status'=>'indexed']);
  }catch(Throwable $e){$d->update(['status'=>'failed','error_message'=>mb_substr($e->getMessage(),0,500)]);throw $e;}
 }
}