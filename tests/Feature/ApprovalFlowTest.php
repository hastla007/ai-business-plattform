<?php
namespace Tests\Feature;
use App\Models\{AgentRun,Approval,Assistant,AssistantVersion,Membership,Organization,User};
use Illuminate\Support\Carbon;
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
  $this->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'approve','parameters'=>['title'=>'attacker edit','description'=>'ignored','priority'=>'urgent']],$headers)->assertOk()->assertJsonPath('status','executed');
  $this->assertDatabaseCount('tickets',1);
  $this->assertDatabaseHas('tickets',['approval_id'=>$proposal['id'],'title'=>'Access issue','priority'=>'normal']);
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
 public function test_rejected_action_is_recorded_without_creating_a_ticket():void {
  [$user,$org,$run]=$this->createOwner();$headers=['X-Organization-Id'=>(string)$org->id];
  $proposal=$this->actingAs($user,'sanctum')->postJson("/api/v1/agent-runs/{$run->id}/actions",['action'=>'create_ticket','parameters'=>['title'=>'Reject me','description'=>'No ticket']],$headers)->assertCreated()->json('approval');
  $this->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'reject'],$headers)->assertOk()->assertJsonPath('status','rejected');
  $this->assertDatabaseHas('approvals',['id'=>$proposal['id'],'status'=>'rejected']);
  $this->assertDatabaseHas('agent_runs',['id'=>$run->id,'status'=>'cancelled']);
  $this->assertDatabaseCount('tickets',0);
 }
 public function test_changed_persisted_approval_payload_is_rejected():void {
  [$user,$org,$run]=$this->createOwner();$headers=['X-Organization-Id'=>(string)$org->id];
  $proposal=$this->actingAs($user,'sanctum')->postJson("/api/v1/agent-runs/{$run->id}/actions",['action'=>'create_ticket','parameters'=>['title'=>'Approved title','description'=>'Approved description']],$headers)->assertCreated()->json('approval');
  Approval::findOrFail($proposal['id'])->update(['action_payload'=>['title'=>'Tampered title','description'=>'Tampered description','priority'=>'urgent']]);
  $this->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'approve'],$headers)->assertStatus(409);
  $this->assertDatabaseCount('tickets',0);
 }
 public function test_expired_approval_is_marked_and_agent_run_is_cancelled():void {
  [$user,$org,$run]=$this->createOwner();$headers=['X-Organization-Id'=>(string)$org->id];
  $proposal=$this->actingAs($user,'sanctum')->postJson("/api/v1/agent-runs/{$run->id}/actions",['action'=>'create_ticket','parameters'=>['title'=>'Expired action','description'=>'Should not run']],$headers)->assertCreated()->json('approval');
  Carbon::setTestNow(now()->addMinutes(16));
  try{$this->artisan('engine:expire-approvals')->assertExitCode(0);}
  finally{Carbon::setTestNow();}
  $this->assertDatabaseHas('approvals',['id'=>$proposal['id'],'status'=>'expired']);
  $this->assertDatabaseHas('agent_runs',['id'=>$run->id,'status'=>'cancelled']);
  $this->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'approve'],$headers)->assertStatus(409);
  $this->assertDatabaseCount('tickets',0);
 }
 public function test_only_one_admin_decision_can_execute_for_an_approval():void {
  [$user,$org,$run]=$this->createOwner();$other=User::create(['name'=>'Second admin','email'=>'second-admin@example.test','password'=>'a-secure-password']);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$other->id,'role'=>'admin']);$headers=['X-Organization-Id'=>(string)$org->id];
  $proposal=$this->actingAs($user,'sanctum')->postJson("/api/v1/agent-runs/{$run->id}/actions",['action'=>'create_ticket','parameters'=>['title'=>'One ticket','description'=>'Exactly once']],$headers)->assertCreated()->json('approval');
  $this->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'approve'],$headers)->assertOk();
  $this->actingAs($other,'sanctum')->postJson("/api/v1/agent-runs/{$run->id}/approve",['approval_id'=>$proposal['id'],'decision'=>'reject'],$headers)->assertStatus(409);
  $this->assertDatabaseCount('tickets',1);$this->assertDatabaseHas('approvals',['id'=>$proposal['id'],'status'=>'executed']);
 }
}
