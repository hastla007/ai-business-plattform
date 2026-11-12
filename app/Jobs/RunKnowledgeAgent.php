<?php
namespace App\Jobs;
use App\Models\{AgentRun,AgentRunEvent,Assistant,AssistantVersion,AuditEvent,Membership};
use App\Services\{AssistantAccessService,KnowledgeRetriever,ModelProvider,ToolRegistry,UsageBudgetService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Throwable;
class RunKnowledgeAgent implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public int $tries=1;public int $timeout=90;
 public function __construct(public int $runId){}
 public function handle(KnowledgeRetriever $search,ModelProvider $provider,ToolRegistry $registry,AssistantAccessService $access,UsageBudgetService $budget):void {
  $run=AgentRun::findOrFail($this->runId);$run->update(['status'=>'running','started_at'=>now()]);$v=AssistantVersion::findOrFail($run->assistant_version_id);
  try {
   if($v->max_steps<2||$v->max_steps>3||$v->timeout_seconds>90)throw new \RuntimeException('Agent limits exceed runtime');
   set_time_limit((int)$v->timeout_seconds);
   if(!Membership::where('organization_id',$run->organization_id)->where('user_id',$run->user_id)->exists())throw new \RuntimeException('Organization membership was revoked');
   $assistant=Assistant::findOrFail($v->assistant_id);
   if(!$access->allows($assistant,(int)$run->user_id,(int)$run->organization_id))throw new \RuntimeException('Assistant access was revoked');
   if(!in_array('search_knowledge',$v->allowed_tools??[],true))throw new \RuntimeException('Tool not allowed');
   $arguments=$registry->validate('search_knowledge',['query'=>$run->input],$v->allowed_tools??[],$run->organization_id);
   $sources=$search->search($arguments['query'],$run->organization_id,$run->user_id,6,$v->allowed_knowledge_sources);
   AgentRunEvent::create(['agent_run_id'=>$run->id,'type'=>'tool.completed','metadata'=>['tool'=>'search_knowledge','result_count'=>count($sources)]]);
   $context=collect($sources)->map(fn($s,$i)=>'[S'.($i+1).'] '.$s['document'].' '.$s['locator'].PHP_EOL.$s['content'])->implode(PHP_EOL.PHP_EOL);
   $profile=$run->modelProfile;
   if(count($v->allowed_models??[])&&!in_array($profile->model,$v->allowed_models,true))throw new \RuntimeException('Model not allowed');
   $messages=[['role'=>'system','content'=>$v->system_instructions.($context?PHP_EOL.PHP_EOL.$context:'')],['role'=>'user','content'=>$run->input]];
   $reservation=$budget->reserve((int)$run->organization_id,(int)$run->user_id,$profile,$run->request_id,$messages,(int)$run->id);
   try{$answer=$provider->chat($messages,$profile);$usage=$answer['usage']??[];if(!isset($usage['completion_tokens'])&&!isset($usage['output_tokens']))$usage['completion_tokens']=(int)ceil(mb_strlen($answer['content'])/4);$budget->complete($reservation['event'],$usage,$reservation);}
   catch(Throwable $e){$budget->complete($reservation['event'],[],$reservation,true);throw $e;}
   $run->update(['status'=>'completed','output'=>$answer['content'],'completed_at'=>now()]);
   AgentRunEvent::create(['agent_run_id'=>$run->id,'type'=>'run.completed','metadata'=>['model'=>$answer['model'],'node_id'=>$answer['node_id']??null,'source_count'=>count($sources),'sources'=>collect($sources)->map(fn($s)=>['document'=>$s['document'],'locator'=>$s['locator']])->values(),'usage'=>$answer['usage']]]);
   AuditEvent::create(['organization_id'=>$run->organization_id,'user_id'=>$run->user_id,'action'=>'agent.completed','request_id'=>$run->request_id,'metadata'=>['run_id'=>$run->id,'assistant_version_id'=>$v->id,'created_at'=>now()]]);
  } catch(Throwable $e) {
   $run->update(['status'=>'failed','error_code'=>'agent_execution_failed','completed_at'=>now()]);
   AgentRunEvent::create(['agent_run_id'=>$run->id,'type'=>'run.failed','metadata'=>['error'=>class_basename($e)]]);
   throw $e;
  }
 }
}
