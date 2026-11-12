<?php
namespace App\Services;
use App\Models\ModelProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
class OpenAiCompatibleProvider implements ModelProvider {
 public function __construct(private ModelRouter $router,private NodeTransport $transport,private UsageBudgetService $budget){}
 private function client(ModelProfile $p) {
  return Http::baseUrl(rtrim($p->base_url?:config('services.ai.base_url'),'/'))->acceptJson()
   ->when(config('services.ai.api_key'),fn($h)=>$h->withToken(config('services.ai.api_key')))
   ->timeout((int)config('services.ai.timeout',60))->retry((int)config('services.ai.max_retries',2),250);
 }
 private function post(ModelProfile $profile,string $path,array $body):array {
  $node=$this->router->route($profile,'internal',$body['model']??null);
  if($node){$result=$this->transport->dispatch($node,['method'=>'POST','path'=>$path,'body'=>$body]);$result['_node_id']=$node->id;return $result;}
  return $this->client($profile)->post($path,$body)->throw()->json();
 }
 private function maxTokens(ModelProfile $p):int{return max(64,min((int)(($p->options??[])['max_output_tokens']??1024),8192));}
 public function chat(array $messages,ModelProfile $profile):array {
  $r=$this->post($profile,'/v1/chat/completions',['model'=>$profile->model,'messages'=>$messages,'temperature'=>0.2,'max_tokens'=>$this->maxTokens($profile)]);
  if(isset($r['error']))throw new RuntimeException('Model node reported an error.');
  $content=data_get($r,'choices.0.message.content');if(!is_string($content))throw new RuntimeException('Model returned no message content.');
  return ['content'=>$content,'usage'=>$r['usage']??[],'model'=>$r['model']??$profile->model,'node_id'=>$r['_node_id']??null];
 }
 public function streamChat(array $messages,ModelProfile $profile):iterable {
  $node=$this->router->route($profile,'internal',$profile->model);
  if($node){
   $r=$this->transport->dispatch($node,['method'=>'POST','path'=>'/v1/chat/completions','body'=>['model'=>$profile->model,'messages'=>$messages,'temperature'=>0.2,'max_tokens'=>$this->maxTokens($profile)]]);
   $content=data_get($r,'choices.0.message.content');if(!is_string($content))throw new RuntimeException('Model returned no message content.');
   yield ['delta'=>$content];yield ['usage'=>$r['usage']??[],'model'=>$r['model']??$profile->model,'node_id'=>$node->id,'done'=>true];return;
  }
  $response=$this->client($profile)->withOptions(['stream'=>true])->post('/v1/chat/completions',['model'=>$profile->model,'messages'=>$messages,'temperature'=>0.2,'max_tokens'=>$this->maxTokens($profile),'stream'=>true])->throw();
  $body=$response->toPsrResponse()->getBody();$buffer='';$usage=[];$model=$profile->model;
  while(!$body->eof()){
   $buffer.=$body->read(4096);
   while(($pos=strpos($buffer,PHP_EOL))!==false){
    $line=trim(substr($buffer,0,$pos));$buffer=substr($buffer,$pos+strlen(PHP_EOL));
    if(!str_starts_with($line,'data:'))continue;
    $data=trim(substr($line,5));if($data==='[DONE]')continue;
    $chunk=json_decode($data,true);if(!is_array($chunk))continue;
    $usage=$chunk['usage']??$usage;$model=$chunk['model']??$model;
    $delta=data_get($chunk,'choices.0.delta.content');if(is_string($delta)&&$delta!=='')yield ['delta'=>$delta];
   }
  }
  yield ['usage'=>$usage,'model'=>$model,'node_id'=>null,'done'=>true];
 }
 public function embed(array $texts,ModelProfile $profile,?int $userId=null):array {
  $reservation=$this->budget->reserveEmbedding($profile,(string)Str::uuid(),$texts,$userId);
  try{
   $r=$this->post($profile,'/v1/embeddings',['model'=>$profile->embedding_model,'input'=>$texts]);
   if(isset($r['error']))throw new RuntimeException('Model node reported an error.');
   $vectors=collect($r['data']??[])->sortBy('index')->pluck('embedding')->all();
   $usage=$r['usage']??['prompt_tokens'=>(int)ceil(mb_strlen(implode(PHP_EOL,$texts))/4)];
   $this->budget->complete($reservation['event'],$usage,$reservation);
   return $vectors;
  }catch(Throwable $e){$this->budget->complete($reservation['event'],[],$reservation,true);throw $e;}
 }
}
