<?php
namespace Tests\Feature;
use App\Models\{Membership,Organization,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TenantIsolationTest extends TestCase {
 use RefreshDatabase;
 public function test_members_cannot_select_another_organizations_context():void {
  $user=User::create(['name'=>'Mara','email'=>'mara@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'One','slug'=>'one','settings'=>[]]);
  $other=Organization::create(['name'=>'Two','slug'=>'two','settings'=>[]]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'member']);
  $this->actingAs($user,'sanctum')->getJson('/api/v1/documents',['X-Organization-Id'=>(string)$other->id])->assertForbidden();
 }
 public function test_member_can_access_their_organizations_document_list():void {
  $user=User::create(['name'=>'Mara','email'=>'mara2@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'One','slug'=>'one-two','settings'=>[]]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'member']);
  $this->actingAs($user,'sanctum')->getJson('/api/v1/documents',['X-Organization-Id'=>(string)$org->id])->assertOk();
 }
}
