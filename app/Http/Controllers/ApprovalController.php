<?php
namespace App\Http\Controllers;
use App\Models\{AgentRun,AgentRunEvent,Approval,AuditEvent,Membership,Ticket};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
class ApprovalController extends Controller {
 public function index(Request $r){return Approval::where('organization_id',$r->attributes->get('organization_id'))->latest()->paginate(25);}
 public function tickets(Request $r){return Ticket::where('organization_id',$r->attributes->get('organization_id'))->latest()->paginate(25);}
 public function propose(Request $r,AgentRun $run){
  $org=(int)$r->attributes->get('organization_id');
  abort_unless((int)$run->organization_id===$org&&((int)$run->user_id===(int)$r->user()->id||in_array($r->attributes->get('organization_role'),['owner','admin'],true)),404);
  abort_unless($run->status==='completed',409);
  $d=$r->validate(['action'=>'required|in:create_ticket','parameters.title'=>'required|string|min:3|max:160','parameters.description'=>'required|string|max:5000','parameters.priority'=>'nullable|in:low,normal,high,urgent']);
  $payload=['title'=>$d['parameters']['title'],'description'=>$d['parameters']['description'],'priority'=>$d['parameters']['priority']??'normal'];
  $hash=$this->payloadHash($payload);
  $approval=DB::transaction(function()use($run,$org,$r,$payload,$hash){
   $locked=AgentRun::where('organization_id',$org)->lockForUpdate()->findOrFail($run->id);
   abort_unless($locked->status==='completed',409);
   if(Approval::where('agent_run_id',$locked->id)->exists())abort(409,'This run already has an approval record.');
   $a=Approval::create(['organization_id'=>$org,'agent_run_id'=>$locked->id,'user_id'=>$r->user()->id,'action_key'=>'create_ticket','action_payload'=>$payload,'payload_hash'=>$hash,'idempotency_key'=>(string)Str::uuid(),'status'=>'pending','expires_at'=>now()->addMinutes(15)]);
   $locked->update(['status'=>'awaiting_approval']);
   AgentRunEvent::create(['agent_run_id'=>$locked->id,'type'=>'action.awaiting_approval','metadata'=>['approval_id'=>$a->id,'action'=>'create_ticket','expires_at'=>$a->expires_at->toISOString()]]);
   AuditEvent::create(['organization_id'=>$org,'user_id'=>$r->user()->id,'action'=>'approval.requested','request_id'=>$locked->request_id,'metadata'=>['approval_id'=>$a->id,'action'=>'create_ticket','created_at'=>now()]]);
   return $a;
  });
  return response()->json(['approval'=>$approval],201);
 }
 public function decide(Request $r,AgentRun $run){
  $d=$r->validate(['approval_id'=>'required|integer','decision'=>'required|in:approve,reject']);
  $org=(int)$r->attributes->get('organization_id');
  $outcome=DB::transaction(function()use($r,$run,$d,$org){
   $lockedRun=AgentRun::where('organization_id',$org)->lockForUpdate()->findOrFail($run->id);
   $approval=Approval::where('organization_id',$org)->where('agent_run_id',$lockedRun->id)->lockForUpdate()->findOrFail($d['approval_id']);
   if($approval->status!=='pending')throw new HttpException(409,'Approval is no longer pending.');
   if($approval->expires_at->isPast()){
    $approval->update(['status'=>'expired']);$lockedRun->update(['status'=>'cancelled']);
    AgentRunEvent::create(['agent_run_id'=>$lockedRun->id,'type'=>'action.expired','metadata'=>['approval_id'=>$approval->id]]);
    AuditEvent::create(['organization_id'=>$org,'user_id'=>$r->user()->id,'action'=>'approval.expired','request_id'=>$lockedRun->request_id,'metadata'=>['approval_id'=>$approval->id,'created_at'=>now()]]);
    return ['status'=>'expired'];
   }
   if(!hash_equals($approval->payload_hash,$this->payloadHash($approval->action_payload??[])))throw new HttpException(409,'Approval payload changed.');
   $membership=Membership::where('organization_id',$org)->where('user_id',$r->user()->id)->lockForUpdate()->first();
   if(!$membership)throw new HttpException(403,'Organization membership was revoked.');
   if($d['decision']==='reject'){
    $approval->update(['status'=>'rejected','approved_by'=>$r->user()->id,'executed_at'=>now()]);
    $lockedRun->update(['status'=>'cancelled']);$actionResult=['decision'=>'rejected'];
   }else{
    if(!in_array($membership->role,['owner','admin'],true))throw new HttpException(403,'Only an organization admin can approve actions.');
    if($approval->action_key!=='create_ticket')throw new HttpException(422,'Unsupported approved action.');
    $p=$approval->action_payload;
    $ticket=Ticket::firstOrCreate(['approval_id'=>$approval->id],['organization_id'=>$org,'created_by'=>$r->user()->id,'title'=>$p['title'],'description'=>$p['description'],'priority'=>$p['priority'],'status'=>'open']);
    $actionResult=['ticket_id'=>$ticket->id];
    $approval->update(['status'=>'executed','approved_by'=>$r->user()->id,'action_result'=>$actionResult,'executed_at'=>now()]);
    $lockedRun->update(['status'=>'completed']);
   }
   AgentRunEvent::create(['agent_run_id'=>$lockedRun->id,'type'=>'action.'.$d['decision'],'metadata'=>['approval_id'=>$approval->id,'action'=>$approval->action_key,'result'=>$actionResult]]);
   AuditEvent::create(['organization_id'=>$org,'user_id'=>$r->user()->id,'action'=>'approval.'.$d['decision'],'request_id'=>$lockedRun->request_id,'metadata'=>['approval_id'=>$approval->id,'action'=>$approval->action_key,'result'=>$actionResult,'created_at'=>now()]]);
   return ['status'=>$approval->status,'result'=>$actionResult];
  });
  if(($outcome['status']??null)==='expired')return response()->json(['error'=>'approval_expired'],409);
  return response()->json($outcome);
 }
 private function payloadHash(array $payload):string{
  $normalize=function($value)use(&$normalize){if(!is_array($value))return $value;if(array_is_list($value))return array_map($normalize,$value);ksort($value);foreach($value as $k=>$v)$value[$k]=$normalize($v);return $value;};
  return hash('sha256',json_encode($normalize($payload),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
 }
}