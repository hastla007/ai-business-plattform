<?php
namespace App\Http\Controllers;
use App\Jobs\RunKnowledgeAgent;
use App\Models\{AgentRun,Assistant,AssistantVersion,ModelProfile};
use App\Services\AssistantAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class AgentController extends Controller {
 public function index(Request $r,AssistantAccessService $access){
  $org=(int)$r->attributes->get('organization_id');$user=(int)$r->user()->id;
  return Assistant::with('versions')->where('organization_id',$org)->where('is_active',true)->get()->filter(fn($a)=>$access->allows($a,$user,$org))->values();
 }
 public function store(Request $r,Assistant $assistant,AssistantAccessService $access){
  $org=(int)$r->attributes->get('organization_id');
  abort_unless($access->allows($assistant,(int)$r->user()->id,$org),404);
  $d=$r->validate(['input'=>'required|string|max:12000']);
  $v=AssistantVersion::where('assistant_id',$assistant->id)->where('is_published',true)->orderByDesc('version')->firstOrFail();
  abort_unless(in_array('search_knowledge',$v->allowed_tools??[],true),403);
  $p=ModelProfile::where('organization_id',$org)->where('is_active',true)->firstOrFail();
  if(count($v->allowed_models??[])&&!in_array($p->model,$v->allowed_models,true))abort(422,'The active model is not permitted for this assistant.');
  $run=AgentRun::create(['organization_id'=>$org,'user_id'=>$r->user()->id,'assistant_version_id'=>$v->id,'model_profile_id'=>$p->id,'status'=>'queued','request_id'=>(string)Str::uuid(),'input'=>$d['input']]);
  RunKnowledgeAgent::dispatch($run->id)->onQueue('agents');
  return response()->json(['run_id'=>$run->id,'request_id'=>$run->request_id,'status'=>$run->status],202);
 }
 public function show(Request $r,AgentRun $run){
  abort_unless((int)$run->organization_id===(int)$r->attributes->get('organization_id')&&(int)$run->user_id===(int)$r->user()->id,404);
  return response()->json($run->load('events'));
 }
}
