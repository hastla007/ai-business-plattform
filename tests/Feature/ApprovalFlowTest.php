<?php
namespace Tests\Feature;
use App\Models\{AgentRun,Assistant,AssistantVersion,Membership,Organization,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class ApprovalFlowTest extends TestCase {
 use RefreshDatabase;
 private function createOwner():array {
  $user=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Acme','slug'=>'acme','settings'=>[]]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'owner']);
  $assistant=Assistant::create(['organization_id'=>$org->id,'name'=>'Knowledge','slug'=>'knowledge','is_active'=>true]);
  $version=AssistantVersion::create(['assistant_id'=>$assistant->id,'version'=>1,'purpose'=>'Test','system_instructions'=>'Test','allowed_models'=>[],'allowed_knowledge_sources'=>[],'allowed_tools'=>['search_knowledge'],'max_steps'=>3,'timeout_seconds'=>60,'approval_policy'=>'never_write','retention_policy'=>'organization_default','is_published'=>true]);
  $run=AgentRun::create(['organization_id'=>$org->id,'user_id'=>$user->id,'assistant_version_id'=>$version->id,'status'=>'completed','request_id'=>(string)Str::uuid(),'input'=>'Create a ticket']);
  return [$user,$org,$run];
 }
 public function test_ticket_is_created_only_after_admin_approval_and_cannot_be_created_twice():void {
  [$user,$org,$run]=$this->createOwner();$headers=['X-Organization-Id'=>(string)$org->id];
  $proposal=$this->actingAs($user,'sanctum')->postJson("/api/v1/agent-runs/{$run->id}/actions",['action'=>'create_ticket','parameters'=>['title'=>'Access issue','description'=>'A user cannot log in.']],$headers)->assertCreated()->json('approval');
  $this->assertDatabaseCount('tickets',0);
  $this->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'approve'],$headers)->assertOk()->assertJsonPath('status','executed');
  $this->assertDatabaseCount('tickets',1);
  $this->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'approve'],$headers)->assertStatus(409);
  $this->assertDatabaseCount('tickets',1);
 }
 public function test_a_member_cannot_approve_a_write_action():void {
  [$user,$org,$run]=$this->createOwner();
  $member=User::create(['name'=>'Member','email'=>'member@example.test','password'=>'a-secure-password']);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$member->id,'role'=>'member']);
  $headers=['X-Organization-Id'=>(string)$org->id];
  $proposal=$this->actingAs($user,'sanctum')->postJson("/api/v1/agent-runs/{$run->id}/actions",['action'=>'create_ticket','parameters'=>['title'=>'Access issue','description'=>'A user cannot log in.']],$headers)->assertCreated()->json('approval');
  $this->actingAs($member,'sanctum')->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'approve'],$headers)->assertForbidden();
  $this->assertDatabaseCount('tickets',0);
 }
}
