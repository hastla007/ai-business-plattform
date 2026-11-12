<?php
namespace Tests\Feature;
use App\Models\{ModelProfile,Organization,User};
use App\Services\UsageBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exception\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
class UsageBudgetTest extends TestCase {
 use RefreshDatabase;
 public function test_daily_token_limit_reserves_input_and_maximum_output_before_provider_call():void {
  $org=Organization::create(['name'=>'Budget','slug'=>'budget-org','settings'=>['daily_token_limit'=>100]]);
  $user=User::create(['name'=>'Budget user','email'=>'budget@example.test','password'=>'a-secure-password']);
  $profile=ModelProfile::create(['organization_id'=>$org->id,'name'=>'test','provider_key'=>'openai-compatible','model'=>'test-chat','embedding_model'=>'test-embed','base_url'=>'http://localhost:8080','options'=>['max_output_tokens'=>64],'is_active'=>true]);
  try{app(UsageBudgetService::class)->reserve($org->id,$user->id,$profile,'6dd2160a-25c8-4fca-b8ef-00a8ca19f012',[['role'=>'user','content'=>str_repeat('a',160)]]);$this->fail('Budget should reject this reservation.');}
  catch(HttpException $e){$this->assertSame(429,$e->getStatusCode());}
  $this->assertDatabaseCount('usage_events',0);
  $this->assertDatabaseHas('audit_events',['organization_id'=>$org->id,'action'=>'usage.rejected','request_id'=>'6dd2160a-25c8-4fca-b8ef-00a8ca19f012']);
 }
 public function test_successful_provider_usage_replaces_the_reserved_estimate():void {
  $org=Organization::create(['name'=>'Budget','slug'=>'budget-org-two','settings'=>['daily_token_limit'=>500]]);
  $user=User::create(['name'=>'Budget user','email'=>'budget2@example.test','password'=>'a-secure-password']);
  $profile=ModelProfile::create(['organization_id'=>$org->id,'name'=>'test','provider_key'=>'openai-compatible','model'=>'test-chat','embedding_model'=>'test-embed','base_url'=>'http://localhost:8080','options'=>['max_output_tokens'=>64,'cost_micros_per_1k_input'=>1000,'cost_micros_per_1k_output'=>1000],'is_active'=>true]);
  $reservation=app(UsageBudgetService::class)->reserve($org->id,$user->id,$profile,'8378931a-fc40-4c9b-aadd-fba66fac03c7',[['role'=>'user','content'=>'Hello']]);
  app(UsageBudgetService::class)->complete($reservation['event'],['prompt_tokens'=>20,'completion_tokens'=>10],$reservation);
  $this->assertDatabaseHas('usage_events',['request_id'=>'8378931a-fc40-4c9b-aadd-fba66fac03c7','input_tokens'=>20,'output_tokens'=>10,'cost_micros'=>30,'status'=>'completed']);
 }
}
