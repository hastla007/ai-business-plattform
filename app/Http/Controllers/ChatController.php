<?php
namespace App\Http\Controllers;
use App\Models\{Assistant,AssistantVersion,AuditEvent,Conversation,Message,ModelProfile};
use App\Services\{AssistantAccessService,KnowledgeRetriever,ModelProvider,UsageBudgetService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;
class ChatController extends Controller {
 public function __invoke(Request $r,KnowledgeRetriever $search,ModelProvider $model,AssistantAccessService $assistantAccess,UsageBudgetService $budget) {
  $d=$r->validate(['message'=>'required|string|max:12000','conversation_id'=>'nullable|integer','assistant_id'=>'nullable|integer','stream'=>'nullable|boolean']);
  $org=(int)$r->attributes->get('organization_id');$user=$r->user();$assistant=null;$version=null;
  if(!empty($d['assistant_id'])){
   $assistant=Assistant::where('organization_id',$org)->findOrFail($d['assistant_id']);
   abort_unless($assistantAccess->allows($assistant,(int)$user->id,$org),404);
   $version=AssistantVersion::where('assistant_id',$assistant->id)->where('is_published',true)->orderByDesc('version')->first();
  }
  $rid=(string)Str::uuid();$started=microtime(true);
  if(!empty($d['conversation_id'])){
   $c=Conversation::where('organization_id',$org)->where('user_id',$user->id)->findOrFail($d['conversation_id']);
   if($c->assistant_id&&(int)$c->assistant_id!==(int)($d['assistant_id']??$c->assistant_id))abort(409,'Conversation assistant cannot be changed.');
   if(!$c->assistant_id&&!empty($d['assistant_id']))$c->update(['assistant_id'=>$d['assistant_id']]);
  }else{$c=Conversation::create(['organization_id'=>$org,'user_id'=>$user->id,'assistant_id'=>$assistant?->id,'title'=>Str::limit($d['message'],80)]);}
  if($c->assistant_id){
   $assistant=Assistant::where('organization_id',$org)->findOrFail($c->assistant_id);
   abort_unless($assistantAccess->allows($assistant,(int)$user->id,$org),404);
   $version??=AssistantVersion::where('assistant_id',$assistant->id)->where('is_published',true)->orderByDesc('version')->first();
  }
  Message::create(['organization_id'=>$org,'conversation_id'=>$c->id,'role'=>'user','content'=>$d['message']]);
  $sources=$search->search($d['message'],$org,$user->id,5,$version?->allowed_knowledge_sources);
  $context=collect($sources)->map(fn($s,$i)=>'[S'.($i+1).'] '.$s['document'].' '.$s['locator'].PHP_EOL.$s['content'])->implode(PHP_EOL.PHP_EOL);
  $p=ModelProfile::where('organization_id',$org)->where('is_active',true)->firstOrFail();
  if($version&&count($version->allowed_models??[])&&!in_array($p->model,$version->allowed_models,true))abort(422,'The active model is not permitted for this assistant.');
  $history=$c->messages()->latest('id')->take(20)->get()->reverse()->map(fn($m)=>['role'=>$m->role,'content'=>$m->content])->values()->all();
  $system=$version?->system_instructions??'Use authorized excerpts only for company facts. Cite sources with [S1]. If evidence is insufficient, say so.';
  $messages=array_merge([['role'=>'system','content'=>$system.($context?PHP_EOL.PHP_EOL.$context:'')]],$history);
  $reservation=$budget->reserve($org,(int)$user->id,$p,$rid,$messages);
  $sourceList=collect($sources)->map(fn($s,$i)=>['ref'=>'S'.($i+1),'document_id'=>$s['document_id'],'document'=>$s['document'],'locator'=>$s['locator']])->values();
  if(!empty($d['stream'])){
   return response()->stream(function()use($model,$messages,$p,$reservation,$budget,$sourceList,$c,$org,$user,$rid,$assistant,$started){
    $content='';$meta=['usage'=>[],'model'=>$p->model,'node_id'=>null];
    $emit=function(string $event,array $data){echo 'event: '.$event.PHP_EOL.'data: '.json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL.PHP_EOL;if(ob_get_level()>0)@ob_flush();flush();};
    try{
     foreach($model->streamChat($messages,$p) as $part){
      if(connection_aborted()){$budget->complete($reservation['event'],[],$reservation,true);return;}
      if(isset($part['delta'])){$content.=$part['delta'];$emit('delta',['text'=>$part['delta']]);}
      if(!empty($part['done']))$meta=array_merge($meta,$part);
     }
     $finalUsage=$meta['usage']??[];if(!isset($finalUsage['completion_tokens'])&&!isset($finalUsage['output_tokens']))$finalUsage['completion_tokens']=(int)ceil(mb_strlen($content)/4);$budget->complete($reservation['event'],$finalUsage,$reservation);
     Message::create(['organization_id'=>$org,'conversation_id'=>$c->id,'role'=>'assistant','content'=>$content,'metadata'=>['model'=>$meta['model'],'usage'=>$meta['usage'],'sources'=>$sourceList,'node_id'=>$meta['node_id']]]);
     AuditEvent::create(['organization_id'=>$org,'user_id'=>$user->id,'action'=>'chat.completed','request_id'=>$rid,'metadata'=>['conversation_id'=>$c->id,'assistant_id'=>$assistant?->id,'model'=>$meta['model'],'node_id'=>$meta['node_id'],'source_count'=>$sourceList->count(),'runtime_ms'=>(int)((microtime(true)-$started)*1000),'created_at'=>now()]]);
     $emit('done',['request_id'=>$rid,'conversation_id'=>$c->id,'sources'=>$sourceList,'model'=>$meta['model'],'model_node_id'=>$meta['node_id'],'usage'=>$meta['usage']]);
    }catch(Throwable $e){$budget->complete($reservation['event'],[],$reservation,true);$emit('error',['message'=>'Die Modellantwort konnte nicht abgeschlossen werden.']);}
   },200,['Content-Type'=>'text/event-stream','Cache-Control'=>'no-cache, no-transform','X-Accel-Buffering'=>'no']);
  }
  try{$answer=$model->chat($messages,$p);$usage=$answer['usage']??[];if(!isset($usage['completion_tokens'])&&!isset($usage['output_tokens']))$usage['completion_tokens']=(int)ceil(mb_strlen($answer['content'])/4);$budget->complete($reservation['event'],$usage,$reservation);}
  catch(Throwable $e){$budget->complete($reservation['event'],[],$reservation,true);throw $e;}
  Message::create(['organization_id'=>$org,'conversation_id'=>$c->id,'role'=>'assistant','content'=>$answer['content'],'metadata'=>['model'=>$answer['model'],'usage'=>$answer['usage'],'sources'=>$sourceList,'node_id'=>$answer['node_id']??null]]);
  AuditEvent::create(['organization_id'=>$org,'user_id'=>$user->id,'action'=>'chat.completed','request_id'=>$rid,'metadata'=>['conversation_id'=>$c->id,'assistant_id'=>$assistant?->id,'model'=>$answer['model'],'node_id'=>$answer['node_id']??null,'source_count'=>$sourceList->count(),'runtime_ms'=>(int)((microtime(true)-$started)*1000),'created_at'=>now()]]);
  return response()->json(['request_id'=>$rid,'conversation_id'=>$c->id,'status'=>'completed','message'=>$answer['content'],'sources'=>$sourceList,'model'=>$answer['model'],'model_node_id'=>$answer['node_id']??null,'usage'=>$answer['usage']]);
 }
}
