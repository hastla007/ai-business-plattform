<?php
namespace App\Http\Controllers;
use App\Models\{Assistant,AssistantAccessRule,Group,Membership};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AssistantAccessController extends Controller {
 public function index(Request $r,Assistant $assistant){$this->scope($r,$assistant);return AssistantAccessRule::where('organization_id',$assistant->organization_id)->where('assistant_id',$assistant->id)->get();}
 public function replace(Request $r,Assistant $assistant){
  $this->scope($r,$assistant);$org=(int)$r->attributes->get('organization_id');
  $d=$r->validate(['users'=>'present|array','users.*'=>'integer','groups'=>'present|array','groups.*'=>'integer','restricted'=>'required|boolean']);
  foreach(array_unique($d['users']) as $id)Membership::where('organization_id',$org)->where('user_id',$id)->firstOrFail();
  foreach(array_unique($d['groups']) as $id)Group::where('organization_id',$org)->findOrFail($id);
  DB::transaction(function()use($assistant,$r,$org,$d){
   $assistant->update(['access_mode'=>$d['restricted']?'restricted':'organization']);
   AssistantAccessRule::where('organization_id',$org)->where('assistant_id',$assistant->id)->delete();
   foreach(array_unique($d['users']) as $id)AssistantAccessRule::create(['organization_id'=>$org,'assistant_id'=>$assistant->id,'subject_type'=>'user','subject_id'=>$id,'created_by'=>$r->user()->id]);
   foreach(array_unique($d['groups']) as $id)AssistantAccessRule::create(['organization_id'=>$org,'assistant_id'=>$assistant->id,'subject_type'=>'group','subject_id'=>$id,'created_by'=>$r->user()->id]);
  });
  return $this->index($r,$assistant);
 }
 private function scope(Request $r,Assistant $assistant):void{abort_unless((int)$assistant->organization_id===(int)$r->attributes->get('organization_id'),404);}
}
