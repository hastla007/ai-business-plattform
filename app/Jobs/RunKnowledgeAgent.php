<?php
namespace App\Jobs;
use App\Models\{AgentRun,AgentRunEvent,AssistantVersion,AuditEvent};
use App\Services\{KnowledgeRetriever,ModelProvider};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Throwable;
class RunKnowledgeAgent implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public int $tries=1;public int $timeout=90;
 public function __construct(public int $runId){}
 public function handle(KnowledgeRetriever $search,ModelProvider $provider,\App\Services\ToolRegistry $registry):void{
  $run=AgentRun::findOrFail($this->runId);$run->update(['status'=>'running','started_at'=>now()]);$v=AssistantVersion::findOrFail($run->assistant_version_id);
  try{
   if($v->max_steps<2||$v->max_steps>3||$v->timeout_seconds>90)throw new \RuntimeException('Agent limits exceed runtime');
   set_time_limit((int)$v->timeout_seconds);
   $allowed=$v->allowed_tools??[];if(!in_array('search_knowledge',$allowed,true))throw new \RuntimeException('Tool not allowed');
   $arguments=$registry->validate('search_knowledge',['query'=>$run->input],$allowed,$run->organization_id);
   $sources=$search->search($arguments['query'],$run->organization_id,$run->user_id,6);
   AgentRunEvent::create(['agent_run_id'=>$run->id,'type'=>'tool.completed','metadata'=>['tool'=>'search_knowledge','result_count'=>count($sources)]]);
   $context=collect($sources)->map(fn($s,$i)=>'[S'.($i+1).'] '.$s['document'].' '.$s['locator']."\n".$s['content'])->implode("\n\n");
   $answer=$provider->chat([['role'=>'system','content'=>$v->system_instructions.($context?"\n\n".$context:'')],['role'=>'user','content'=>$run->input]],$run->modelProfile);
   $run->update(['status'=>'completed','output'=>$answer['content'],'completed_at'=>now()]);
   AgentRunEvent::create(['agent_run_id'=>$run->id,'type'=>'run.completed','metadata'=>['model'=>$answer['model'],'source_count'=>count($sources),'sources'=>collect($sources)->map(fn($s)=>['document'=>$s['document'],'locator'=>$s['locator']])->values(),'usage'=>$answer['usage']]]);
   AuditEvent::create(['organization_id'=>$run->organization_id,'user_id'=>$run->user_id,'action'=>'agent.completed','request_id'=>$run->request_id,'metadata'=>['run_id'=>$run->id,'assistant_version_id'=>$v->id,'created_at'=>now()]]);
  }catch(Throwable $e){$run->update(['status'=>'failed','error_code'=>'agent_execution_failed','completed_at'=>now()]);AgentRunEvent::create(['agent_run_id'=>$run->id,'type'=>'run.failed','metadata'=>['error'=>class_basename($e)]]);throw $e;}
 }
}