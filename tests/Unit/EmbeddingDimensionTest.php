<?php
namespace Tests\Unit;
use App\Models\ModelProfile;
use App\Models\UsageEvent;
use App\Services\{ModelRouter,NodeTransport,OpenAiCompatibleProvider,UsageBudgetService};
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
class EmbeddingDimensionTest extends TestCase {
 public function test_provider_rejects_vectors_that_do_not_match_fixed_pgvector_dimension():void {
  Http::fake(['*'=>Http::response(['data'=>[['index'=>0,'embedding'=>[0.1,0.2]]],'usage'=>[]],200)]);
  $router=\Mockery::mock(ModelRouter::class);$router->shouldReceive('route')->once()->andReturn(null);
  $transport=\Mockery::mock(NodeTransport::class);
  $budget=\Mockery::mock(UsageBudgetService::class);
  $event=new UsageEvent();
  $budget->shouldReceive('reserveEmbedding')->once()->andReturn(['event'=>$event,'rate_in'=>0,'rate_out'=>0]);
  $budget->shouldReceive('complete')->once()->with($event,[],\Mockery::type('array'),true);
  $profile=ModelProfile::make(['organization_id'=>1,'model'=>'chat-model','embedding_model'=>'embed-model','embedding_dimensions'=>1536,'base_url'=>'https://models.example.test']);
  $provider=new OpenAiCompatibleProvider($router,$transport,$budget);
  $this->expectException(RuntimeException::class);
  $this->expectExceptionMessage('Embedding dimension does not match the configured vector index.');
  $provider->embed(['sample'],$profile,1);
 }
}
