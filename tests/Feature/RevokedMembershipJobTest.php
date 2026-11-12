<?php
namespace Tests\Feature;
use App\Jobs\RunKnowledgeAgent;
use App\Models\{AgentRun,Assistant,AssistantVersion,Membership,ModelProfile,Organization,User};
use App\Services\{AssistantAccessService,KnowledgeRetriever,ModelProvider,ToolRegistry,UsageBudgetService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class RevokedMembershipJobTest extends TestCase {
 use RefreshDatabase;
 public function test_queued_agent_run_fails_if_membership_is_revoked_before_execution():void {
  $user=User::create(['name'=>'Revoked','email'=>'revoked-job@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Revocation','slug'=>'revoked-job-org','settings'=>[]]);
  $membership=Membership::create(['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'member']);
  $assistant=Assistant::create(['organization_id'=>$org->id,'name'=>'Knowledge','slug'=>'revocation-knowledge','is_active'=>true]);
  $version=AssistantVersion::create(['assistant_id'=>$assistant->id,'version'=>1,'purpose'=>'Read only','system_instructions'=>'Use sources.','allowed_models'=>[],'allowed_knowledge_sources'=>[],'allowed_tools'=>['search_knowledge'],'max_steps'=>3,'timeout_seconds'=>60,'approval_policy'=>'never_write','retention_policy'=>'organization_default','is_published'=>true]);
  $profile=ModelProfile::create(['organization_id'=>$org->id,'name'=>'Test profile','provider_key'=>'openai-compatible','model'=>'test-chat','embedding_model'=>'test-embed','base_url'=>'http://127.0.0.1:9','is_active'=>true]);
  $run=AgentRun::create(['organization_id'=>$org->id,'user_id'=>$user->id,'assistant_version_id'=>$version->id,'model_profile_id'=>$profile->id,'status'=>'queued','request_id'=>(string)Str::uuid(),'input'=>'Read my document']);
  $membership->delete();
  $this->expectException(\RuntimeException::class);
  try {
   (new RunKnowledgeAgent($run->id))->handle(app(KnowledgeRetriever::class),app(ModelProvider::class),app(ToolRegistry::class),app(AssistantAccessService::class),app(UsageBudgetService::class));
  } finally {
   $this->assertDatabaseHas('agent_runs',['id'=>$run->id,'status'=>'failed','error_code'=>'agent_execution_failed']);
   $this->assertDatabaseHas('agent_run_events',['agent_run_id'=>$run->id,'type'=>'run.failed']);
  }
 }
}
