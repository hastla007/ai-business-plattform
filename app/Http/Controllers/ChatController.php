<?php
namespace App\Http\Controllers;
use App\Models\{AuditEvent,Conversation,Message,ModelProfile};
use App\Services\{KnowledgeRetriever,ModelProvider};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ChatController extends Controller {
 public function __invoke(Request $r,KnowledgeRetriever $search,ModelProvider $model) {
  $d=$r->validate(['message'=>'required|string|max:12000','conversation_id'=>'nullable|integer']);
  $org=(int)$r->attributes->get('organization_id');$user=$r->user();$rid=(string)Str::uuid();
  $c=isset($d['conversation_id'])?Conversation::where('organization_id',$org)->where('user_id',$user->id)->findOrFail($d['conversation_id']):Conversation::create(['organization_id'=>$org,'user_id'=>$user->id,'title'=>Str::limit($d['message'],80)]);
  Message::create(['organization_id'=>$org,'conversation_id'=>$c->id,'role'=>'user','content'=>$d['message']]);
  $sources=$search->search($d['message'],$org,$user->id);
  $context=collect($sources)->map(fn($s,$i)=>'[S'.($i+1).'] '.$s['document'].' '.$s['locator']."\n".$s['content'])->implode("\n\n");
  $p=ModelProfile::where('organization_id',$org)->where('is_active',true)->firstOrFail();
  $history=$c->messages()->latest('id')->take(20)->get()->reverse()->map(fn($m)=>['role'=>$m->role,'content'=>$m->content])->values()->all();
  $messages=array_merge([['role'=>'system','content'=>'Use authorized excerpts only for company facts. Cite sources with [S1]. If evidence is insufficient, say so.'.($context?PHP_EOL.PHP_EOL.$context:'')]],$history);
  $answer=$model->chat($messages,$p);
  Message::create(['organization_id'=>$org,'conversation_id'=>$c->id,'role'=>'assistant','content'=>$answer['content'],'metadata'=>['model'=>$answer['model'],'usage'=>$answer['usage'],'source_count'=>count($sources)]]);
  AuditEvent::create(['organization_id'=>$org,'user_id'=>$user->id,'action'=>'chat.completed','request_id'=>$rid,'metadata'=>['conversation_id'=>$c->id,'model'=>$answer['model'],'source_count'=>count($sources),'created_at'=>now()]]);
  return response()->json(['request_id'=>$rid,'conversation_id'=>$c->id,'status'=>'completed','message'=>$answer['content'],'sources'=>collect($sources)->map(fn($s,$i)=>['ref'=>'S'.($i+1),'document'=>$s['document'],'locator'=>$s['locator']])->values(),'model'=>$answer['model'],'usage'=>$answer['usage']]);
 }
}