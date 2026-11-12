<?php
namespace App\Http\Controllers;
use App\Models\{AuditEvent,Membership,Node,Site};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class NodeController extends Controller {
 public function index(Request $r){
  $org=(int)$r->attributes->get('organization_id');
  return Node::where('organization_id',$org)->with('site:id,name')->withCount(['commands as pending_commands'=>fn($q)=>$q->whereIn('status',['queued','running'])])->latest()->paginate(50);
 }
 public function enroll(Request $r){
  $org=(int)$r->attributes->get('organization_id');
  $d=$r->validate(['name'=>'required|string|max:120','site_id'=>'nullable|integer','privacy_class'=>'required|in:public,internal,confidential','hardware_profile'=>'nullable|array','max_parallel'=>'nullable|integer|min:1|max:64']);
  if(!empty($d['site_id']))Site::where('organization_id',$org)->findOrFail($d['site_id']);
  $secret=Str::random(64);
  $node=Node::create(['organization_id'=>$org,'site_id'=>$d['site_id']??null,'name'=>$d['name'],'status'=>'offline','token_hash'=>hash('sha256',$secret),'privacy_class'=>$d['privacy_class'],'hardware_profile'=>$d['hardware_profile']??[],'max_parallel'=>$d['max_parallel']??1,'capabilities'=>[]]);
  AuditEvent::create(['organization_id'=>$org,'user_id'=>$r->user()->id,'action'=>'node.enrolled','metadata'=>['node_id'=>$node->id,'created_at'=>now()]]);
  return response()->json(['node'=>$node,'token'=>$secret,'token_notice'=>'Copy this token now. It is shown only once.'],201);
 }
 public function disable(Request $r,Node $node){
  abort_unless((int)$node->organization_id===(int)$r->attributes->get('organization_id'),404);
  $node->update(['status'=>'disabled']);$node->commands()->whereIn('status',['queued','running'])->update(['status'=>'expired']);
  AuditEvent::create(['organization_id'=>$node->organization_id,'user_id'=>$r->user()->id,'action'=>'node.disabled','metadata'=>['node_id'=>$node->id,'created_at'=>now()]]);
  return response()->noContent();
 }
 public function heartbeat(Request $r){
  $node=$r->attributes->get('node');
  $d=$r->validate(['version'=>'required|string|max:40','capabilities'=>'required|array','capabilities.models'=>'array','capabilities.models.*'=>'string|max:120','capabilities.embedding_models'=>'nullable|array','capabilities.embedding_models.*'=>'string|max:120','capabilities.embedding_dimensions'=>'nullable|array','capabilities.embedding_dimensions.*'=>'integer|distinct|in:1536','hardware_profile'=>'nullable|array']);
  $node->update(['status'=>'online','last_seen_at'=>now(),'version'=>$d['version'],'capabilities'=>$d['capabilities'],'hardware_profile'=>$d['hardware_profile']??$node->hardware_profile]);
  return response()->json(['status'=>'online','node_id'=>$node->id]);
 }
}