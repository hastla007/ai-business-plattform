<?php
namespace Tests\Feature;
use App\Models\{Assistant,AssistantAccessRule,Membership,Organization,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AssistantAccessTest extends TestCase {
 use RefreshDatabase;
 public function test_assistant_acl_limits_list_and_invocation_to_granted_member():void {
  $owner=User::create(['name'=>'Owner','email'=>'owner@example.test','password'=>'a-secure-password']);
  $member=User::create(['name'=>'Member','email'=>'member-assistant@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Acme','slug'=>'acme-assistant','settings'=>[]]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$owner->id,'role'=>'owner']);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$member->id,'role'=>'member']);
  $assistant=Assistant::create(['organization_id'=>$org->id,'name'=>'Restricted','slug'=>'restricted','is_active'=>true]);
  AssistantAccessRule::create(['organization_id'=>$org->id,'assistant_id'=>$assistant->id,'subject_type'=>'user','subject_id'=>$owner->id,'created_by'=>$owner->id]);
  $this->actingAs($member,'sanctum')->getJson('/api/v1/agents',['X-Organization-Id'=>(string)$org->id])->assertOk()->assertJsonCount(0);
  $this->postJson("/api/v1/agents/{$assistant->id}/runs",["input"=>"test"],['X-Organization-Id'=>(string)$org->id])->assertNotFound();
 }
 public function test_assistant_access_rules_can_only_reference_same_organization_members():void {
  $owner=User::create(['name'=>'Owner','email'=>'owner2@example.test','password'=>'a-secure-password']);
  $other=User::create(['name'=>'Other','email'=>'other@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Acme','slug'=>'acme-assistant-two','settings'=>[]]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$owner->id,'role'=>'owner']);
  $assistant=Assistant::create(['organization_id'=>$org->id,'name'=>'Restricted','slug'=>'restricted-two','is_active'=>true]);
  $this->actingAs($owner,'sanctum')->putJson("/api/v1/assistants/{$assistant->id}/access",['users'=>[$other->id],'groups'=>[],'restricted'=>true],['X-Organization-Id'=>(string)$org->id])->assertNotFound();
  $this->assertDatabaseCount('assistant_access_rules',0);
 }
}
