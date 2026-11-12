<?php
namespace Tests\Feature;
use App\Models\{Membership,Organization,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SaaSPlanEnforcementTest extends TestCase {
 use RefreshDatabase;
 private function tenant(array $settings):array {
  $user=User::create(['name'=>'Plan Admin','email'=>'plan-admin@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Plan Tenant','slug'=>'plan-tenant','settings'=>$settings]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'owner']);
  return [$user,$org];
 }
 public function test_expired_saas_plan_is_read_only_after_grace_and_can_be_renewed():void {
  config(['services.auth.deployment_mode'=>'saas']);
  [$user,$org]=$this->tenant(['deployment_mode'=>'saas','plan'=>'trial','license_status'=>'expired','plan_expires_at'=>now()->subDays(10)->toIso8601String(),'plan_grace_days'=>7]);
  $headers=['X-Organization-Id'=>(string)$org->id];
  $this->actingAs($user,'sanctum')->getJson('/api/v1/documents',$headers)->assertOk();
  $this->actingAs($user,'sanctum')->postJson('/api/v1/documents',[],$headers)->assertStatus(402)->assertJsonPath('error','plan_expired');
  $this->actingAs($user,'sanctum')->putJson('/api/v1/organization/settings',['license_status'=>'active','plan_expires_at'=>now()->addDays(30)->toDateString()],$headers)->assertOk();
  $this->actingAs($user,'sanctum')->postJson('/api/v1/documents',[],$headers)->assertStatus(422);
 }
 public function test_new_saas_tenant_receives_configured_trial_plan_and_quota():void {
  config(['services.auth.deployment_mode'=>'saas','services.auth.registration_enabled'=>true,'services.auth.trial_days'=>21,'services.auth.plans.trial'=>['max_users'=>9,'monthly_cost_limit_micros'=>12345]]);
  $this->postJson('/api/v1/register',['name'=>'New Tenant Admin','email'=>'new-admin@example.test','password'=>'a-very-secure-password','organization_name'=>'Trial Company'])->assertCreated();
  $org=Organization::where('name','Trial Company')->firstOrFail();$settings=$org->settings;
  $this->assertSame('trial',$settings['plan']);$this->assertSame('active',$settings['license_status']);$this->assertSame(9,$settings['max_users']);$this->assertSame(12345,$settings['monthly_cost_limit_micros']);
  $this->assertTrue(now()->addDays(20)->lt(\Illuminate\Support\Carbon::parse($settings['plan_expires_at'])));
 }
 public function test_on_premise_deployment_does_not_require_a_plan_license():void {
  config(['services.auth.deployment_mode'=>'onprem']);
  [$user,$org]=$this->tenant(['deployment_mode'=>'onprem','license_status'=>'expired','plan_expires_at'=>now()->subYear()->toIso8601String()]);
  $this->actingAs($user,'sanctum')->postJson('/api/v1/documents',[],['X-Organization-Id'=>(string)$org->id])->assertStatus(422);
 }
}
