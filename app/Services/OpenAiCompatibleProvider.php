<?php
namespace App\Services;
use App\Models\ModelProfile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class OpenAiCompatibleProvider implements ModelProvider {
 public function __construct(private ModelRouter $router,private NodeTransport $transport){}
 private function client(ModelProfile $p) {
  return Http::baseUrl(rtrim($p->base_url?:config('services.ai.base_url'),'/'))->acceptJson()
   ->when(config('services.ai.api_key'),fn($h)=>$h->withToken(config('services.ai.api_key')))
   ->timeout((int)config('services.ai.timeout',60))->retry((int)config('services.ai.max_retries',2),250);
 }
 private function post(ModelProfile $profile,string $path,array $body):array {
  $node=$this->router->route($profile,'internal',$body['model']??null);
  if($node){
   $result=$this->transport->dispatch($node,['method'=>'POST','path'=>$path,'body'=>$body]);
   $result['_node_id']=$node->id;
   return $result;
  }
  return $this->client($profile)->post($path,$body)->throw()->json();
 }
 public function chat(array $messages,ModelProfile $profile): array {
  $r=$this->post($profile,'/v1/chat/completions',['model'=>$profile->model,'messages'=>$messages,'temperature'=>0.2]);
  if(isset($r['error']))throw new RuntimeException('Model node reported an error.');
  $content=data_get($r,'choices.0.message.content');
  if(!is_string($content)) throw new RuntimeException('Model returned no message content.');
  return ['content'=>$content,'usage'=>$r['usage']??[],'model'=>$r['model']??$profile->model,'node_id'=>$r['_node_id']??null];
 }
 public function embed(array $texts,ModelProfile $profile): array {
  $r=$this->post($profile,'/v1/embeddings',['model'=>$profile->embedding_model,'input'=>$texts]);
  if(isset($r['error']))throw new RuntimeException('Model node reported an error.');
  return collect($r['data']??[])->sortBy('index')->pluck('embedding')->all();
 }
}