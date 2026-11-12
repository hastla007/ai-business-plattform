<?php
namespace App\Http\Controllers;
use App\Jobs\IngestDocument;
use App\Models\{Document,KnowledgeSource};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class DocumentController extends Controller {
 public function index(Request $r){return Document::where('organization_id',$r->attributes->get('organization_id'))->where(fn($q)=>$q->where('access_scope','organization')->orWhere('created_by',$r->user()->id))->latest()->paginate(25);}
 public function store(Request $r){return $this->saveUpload($r,null);}
 public function update(Request $r,Document $document){$this->authorizeDocument($r,$document);return $this->saveUpload($r,$document);}
 private function saveUpload(Request $r,?Document $old){
  $d=$r->validate(['file'=>'required|file|max:51200|mimes:txt,md,pdf,docx','knowledge_source_id'=>'nullable|integer','access_scope'=>'nullable|in:organization,owner']);
  $org=(int)$r->attributes->get('organization_id');
  if(!empty($d['knowledge_source_id'])) KnowledgeSource::where('organization_id',$org)->findOrFail($d['knowledge_source_id']);
  $f=$d['file'];$hash=hash_file('sha256',$f->getRealPath());
  $path=Storage::disk('local')->putFileAs('organizations/'.$org,$f,Str::uuid().'.'.$f->getClientOriginalExtension());
  if($old){Storage::disk($old->disk)->delete($old->path);$old->chunks()->delete();$old->update(['path'=>$path,'name'=>$f->getClientOriginalName(),'content_hash'=>$hash,'version'=>$old->version+1,'status'=>'queued','error_message'=>null,'mime_type'=>$f->getMimeType(),'size'=>$f->getSize()]);$doc=$old;}
  else $doc=Document::create(['organization_id'=>$org,'knowledge_source_id'=>$d['knowledge_source_id']??null,'created_by'=>$r->user()->id,'name'=>$f->getClientOriginalName(),'disk'=>'local','path'=>$path,'content_hash'=>$hash,'version'=>1,'classification'=>'internal','access_scope'=>$d['access_scope']??'organization','status'=>'queued','mime_type'=>$f->getMimeType(),'size'=>$f->getSize()]);
  IngestDocument::dispatch($doc->id)->onQueue('documents');return response()->json($doc,202);
 }
 private function authorizeDocument(Request $r,Document $d){abort_unless($d->organization_id===$r->attributes->get('organization_id'),404);abort_unless($d->access_scope==='organization'||$d->created_by===$r->user()->id,403);}
 public function destroy(Request $r,Document $document){$this->authorizeDocument($r,$document);Storage::disk($document->disk)->delete($document->path);$document->delete();return response()->noContent();}
}