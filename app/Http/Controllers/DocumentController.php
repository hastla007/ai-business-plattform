<?php
namespace App\Http\Controllers;
use App\Jobs\IngestDocument;
use App\Models\{Document,Group,KnowledgeSource};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class DocumentController extends Controller {
 public function index(Request $r){
  $org=(int)$r->attributes->get('organization_id');$user=$r->user()->id;
  return Document::where('organization_id',$org)->where(function($q)use($org,$user){
   $q->where('access_scope','organization')
    ->orWhere(fn($owner)=>$owner->where('access_scope','owner')->where('created_by',$user))
    ->orWhereIn('group_id',\Illuminate\Support\Facades\DB::table('group_memberships')->select('group_id')->where('organization_id',$org)->where('user_id',$user));
  })->latest()->paginate(25);
 }
 public function store(Request $r){return $this->saveUpload($r,null);}
 public function update(Request $r,Document $document){$this->authorizeDocument($r,$document);return $this->saveUpload($r,$document);}
 private function saveUpload(Request $r,?Document $old){
  $d=$r->validate(['file'=>'required|file|max:51200|mimes:txt,md,pdf,docx','knowledge_source_id'=>'nullable|integer','access_scope'=>'nullable|in:organization,group,owner','group_id'=>'nullable|integer']);
  $org=(int)$r->attributes->get('organization_id');
  if(!empty($d['knowledge_source_id']))KnowledgeSource::where('organization_id',$org)->findOrFail($d['knowledge_source_id']);
  if(!$old&&($d['access_scope']??'organization')==='group'){
   if(empty($d['group_id']))abort(422,'group_id is required for group access');
   Group::where('organization_id',$org)->whereKey($d['group_id'])->whereHas('members',fn($q)=>$q->where('users.id',$r->user()->id))->firstOrFail();
  }
  $file=$d['file'];$hash=hash_file('sha256',$file->getRealPath());
  $disk=config('filesystems.default','local');$path=Storage::disk($disk)->putFileAs('organizations/'.$org,$file,Str::uuid().'.'.$file->getClientOriginalExtension());
  if($old){
   Storage::disk($old->disk)->delete($old->path);$old->chunks()->delete();
   $old->update(['path'=>$path,'name'=>$file->getClientOriginalName(),'content_hash'=>$hash,'version'=>$old->version+1,'status'=>'queued','error_message'=>null,'mime_type'=>$file->getMimeType(),'size'=>$file->getSize()]);
   $document=$old;
  }else{
   $document=Document::create(['organization_id'=>$org,'knowledge_source_id'=>$d['knowledge_source_id']??null,'created_by'=>$r->user()->id,'group_id'=>$d['group_id']??null,'name'=>$file->getClientOriginalName(),'disk'=>$disk,'path'=>$path,'content_hash'=>$hash,'version'=>1,'classification'=>'internal','access_scope'=>$d['access_scope']??'organization','status'=>'queued','mime_type'=>$file->getMimeType(),'size'=>$file->getSize()]);
  }
  IngestDocument::dispatch($document->id)->onQueue('documents');
  return response()->json($document,202);
 }
 private function authorizeDocument(Request $r,Document $d){
  abort_unless((int)$d->organization_id===(int)$r->attributes->get('organization_id'),404);
  if($d->access_scope==='owner')abort_unless((int)$d->created_by===(int)$r->user()->id,403);
  if($d->access_scope==='group')abort_unless(Group::whereKey($d->group_id)->whereHas('members',fn($q)=>$q->where('users.id',$r->user()->id))->exists(),403);
  if($d->access_scope==='organization')abort_unless(in_array($r->attributes->get('organization_role'),['owner','admin'],true),403);
 }
 public function destroy(Request $r,Document $document){
  $this->authorizeDocument($r,$document);Storage::disk($document->disk)->delete($document->path);$document->delete();return response()->noContent();
 }
}