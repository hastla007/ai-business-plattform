<?php
namespace App\Console\Commands;
use App\Models\{AgentRun,AgentRunEvent,Approval,AuditEvent};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class ExpirePendingApprovals extends Command {
 protected $signature='engine:expire-approvals';
 protected $description='Expire pending actions and cancel their agent runs';
 public function handle():int {
  Approval::where('status','pending')->where('expires_at','<=',now())->orderBy('id')->chunkById(100,function($rows){
   foreach($rows as $row)DB::transaction(function()use($row){
    $approval=Approval::whereKey($row->id)->lockForUpdate()->first();
    if(!$approval||$approval->status!=='pending'||$approval->expires_at->isFuture())return;
    $approval->update(['status'=>'expired']);
    $run=AgentRun::whereKey($approval->agent_run_id)->lockForUpdate()->first();
    if($run){$run->update(['status'=>'cancelled']);AgentRunEvent::create(['agent_run_id'=>$run->id,'type'=>'action.expired','metadata'=>['approval_id'=>$approval->id]]);}
    AuditEvent::create(['organization_id'=>$approval->organization_id,'user_id'=>null,'action'=>'approval.expired','request_id'=>$run?->request_id,'metadata'=>['approval_id'=>$approval->id,'created_at'=>now()]]);
   });
  });
  return self::SUCCESS;
 }
}