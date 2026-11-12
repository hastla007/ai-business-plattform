<?php
namespace Tests\Feature;
use App\Models\{Membership,ModelProfile,Organization,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class DocumentReindexAuthorizationTest extends TestCase {
 use RefreshDatabase;
 public function test_only_owner_or_admin_can_reindex_organization_documents():void {
  $owner=User::create(['name'=>'Owner','email'=>'owner-reindex@example.test','password'=>'a-secure-password']);
  $member=User::create(['name'=>'Member','email'=>'member-reindex@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Reindex Org','slug'=>'reindex-org','settings'=>['deployment_mode'=>'onprem']]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$owner->id,'role'=>'owner']);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$member->id,'role'=>'member']);
  ModelProfile::create(['organization_id'=>$org->id,'name'=>'Local','provider_key'=>'openai-compatible','model'=>'chat','embedding_model'=>'embed','base_url'=>'http://models.test','is_active'=>true]);
  $headers=['X-Organization-Id'=>(string)$org->id];
  $this->actingAs($member,'sanctum')->postJson('/api/v1/documents/reindex',[],$headers)->assertForbidden();
  $this->actingAs($owner,'sanctum')->postJson('/api/v1/documents/reindex',[],$headers)->assertAccepted()->assertJsonPath('queued',0);
 }
}
