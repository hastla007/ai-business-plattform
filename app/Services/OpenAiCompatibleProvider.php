<?php
namespace App\Services;
use App\Models\ModelProfile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class OpenAiCompatibleProvider implements ModelProvider {
 private function client(ModelProfile $p) {
  return Http::baseUrl(rtrim($p->base_url?:config('services.ai.base_url'),'/'))->acceptJson()
   ->when(config('services.ai.api_key'),fn($h)=>$h->withToken(config('services.ai.api_key')))
   ->timeout((int)config('services.ai.timeout',60))->retry((int)config('services.ai.max_retries',2),250);
 }
 public function chat(array $messages,ModelProfile $profile): array {
  $r=$this->client($profile)->post('/v1/chat/completions',['model'=>$profile->model,'messages'=>$messages,'temperature'=>0.2])->throw()->json();
  $content=data_get($r,'choices.0.message.content');
  if(!is_string($content)) throw new RuntimeException('Model returned no message content.');
  return ['content'=>$content,'usage'=>$r['usage']??[],'model'=>$r['model']??$profile->model];
 }
 public function embed(array $texts,ModelProfile $profile): array {
  $r=$this->client($profile)->post('/v1/embeddings',['model'=>$profile->embedding_model,'input'=>$texts])->throw()->json();
  return collect($r['data']??[])->sortBy('index')->pluck('embedding')->all();
 }
}