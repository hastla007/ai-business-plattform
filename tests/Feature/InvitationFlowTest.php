<?php
namespace Tests\Feature;
use App\Models\{Membership,Organization,OrganizationInvitation,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
class InvitationFlowTest extends TestCase {
 use RefreshDatabase;
 public function test_invitation_token_is_one_time_and_assigns_the_approved_role():void {
  $owner=User::create(['name'=>'Owner','email'=>'invite-owner@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Invites','slug'=>'invites-org','settings'=>[]]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$owner->id,'role'=>'owner']);
  $token=Str::random(64);
  $invite=OrganizationInvitation::create(['organization_id'=>$org->id,'email'=>'new-user@example.test','role'=>'member','token_hash'=>hash('sha256',$token),'invited_by'=>$owner->id,'expires_at'=>now()->addDays(3)]);
  $this->postJson('/api/v1/invitations/register',['token'=>$token,'name'=>'New User','password'=>'a-secure-password'])->assertCreated();
  $this->assertDatabaseHas('memberships',['organization_id'=>$org->id,'user_id'=>User::where('email','new-user@example.test')->value('id'),'role'=>'member']);
  $this->assertNotNull($invite->fresh()->accepted_at);
  $this->postJson('/api/v1/invitations/register',['token'=>$token,'name'=>'Replay','password'=>'a-secure-password'])->assertNotFound();
 }
 public function test_public_registration_can_be_disabled_for_saas():void {
  config(['services.auth.registration_enabled'=>false]);
  $this->postJson('/api/v1/register',['name'=>'X','email'=>'x@example.test','password'=>'a-secure-password','organization_name'=>'X Ltd'])->assertForbidden();
 }
}
