<?php
namespace Tests\Feature;
use App\Models\{Document,Group,Membership,Organization,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Queue,Storage};
use Tests\TestCase;
class DocumentOwnershipAuthorizationTest extends TestCase {
 use RefreshDatabase;
 private User $owner; private User $member; private User $admin; private Organization $org; private Group $group; private array $headers;
 protected function setUp():void {
  parent::setUp();Storage::fake('local');Queue::fake();
  $this->owner=User::create(['name'=>'Owner','email'=>'owner-rights@example.test','password'=>'a-secure-password']);
  $this->member=User::create(['name'=>'Member','email'=>'member-rights@example.test','password'=>'a-secure-password']);
  $this->admin=User::create(['name'=>'Admin','email'=>'admin-rights@example.test','password'=>'a-secure-password']);
  $this->org=Organization::create(['name'=>'Rights','slug'=>'document-rights','settings'=>[]]);
  Membership::create(['organization_id'=>$this->org->id,'user_id'=>$this->owner->id,'role'=>'member']);Membership::create(['organization_id'=>$this->org->id,'user_id'=>$this->member->id,'role'=>'member']);Membership::create(['organization_id'=>$this->org->id,'user_id'=>$this->admin->id,'role'=>'admin']);
  $this->group=Group::create(['organization_id'=>$this->org->id,'name'=>'Shared']);
  DB::table('group_memberships')->insert([['organization_id'=>$this->org->id,'group_id'=>$this->group->id,'user_id'=>$this->owner->id,'created_at'=>now(),'updated_at'=>now()],['organization_id'=>$this->org->id,'group_id'=>$this->group->id,'user_id'=>$this->member->id,'created_at'=>now(),'updated_at'=>now()]]);
  $this->headers=['X-Organization-Id'=>(string)$this->org->id];
 }
 private function document(User $owner,string $scope,?int $groupId=null):Document {
  return Document::create(['organization_id'=>$this->org->id,'created_by'=>$owner->id,'group_id'=>$groupId,'name'=>$scope.'.txt','disk'=>'local','path'=>'organizations/'.$scope.'.txt','content_hash'=>hash('sha256',$scope),'version'=>1,'classification'=>'internal','access_scope'=>$scope,'status'=>'indexed','size'=>4]);
 }
 public function test_owner_scope_is_visible_only_to_owner_and_owner_can_delete():void {
  $doc=$this->document($this->owner,'owner');
  $this->actingAs($this->owner,'sanctum')->getJson('/api/v1/documents',$this->headers)->assertOk()->assertJsonFragment(['id'=>$doc->id]);
  $this->actingAs($this->member,'sanctum')->getJson('/api/v1/documents',$this->headers)->assertOk()->assertJsonMissing(['id'=>$doc->id]);
  $this->actingAs($this->member,'sanctum')->deleteJson('/api/v1/documents/'.$doc->id,[],$this->headers)->assertForbidden();
  $this->actingAs($this->owner,'sanctum')->deleteJson('/api/v1/documents/'.$doc->id,[],$this->headers)->assertNoContent();
 }
 public function test_organization_scope_is_readable_by_members_but_writable_only_by_admins():void {
  $doc=$this->document($this->owner,'organization');
  $this->actingAs($this->member,'sanctum')->getJson('/api/v1/documents',$this->headers)->assertOk()->assertJsonFragment(['id'=>$doc->id]);
  $this->actingAs($this->member,'sanctum')->deleteJson('/api/v1/documents/'.$doc->id,[],$this->headers)->assertForbidden();
  $this->actingAs($this->admin,'sanctum')->deleteJson('/api/v1/documents/'.$doc->id,[],$this->headers)->assertNoContent();
 }
 public function test_group_scope_requires_membership_and_group_members_cannot_delete():void {
  $doc=$this->document($this->owner,'group',$this->group->id);
  $outsider=User::create(['name'=>'Outsider','email'=>'outsider-rights@example.test','password'=>'a-secure-password']);Membership::create(['organization_id'=>$this->org->id,'user_id'=>$outsider->id,'role'=>'member']);
  $this->actingAs($this->member,'sanctum')->getJson('/api/v1/documents',$this->headers)->assertOk()->assertJsonFragment(['id'=>$doc->id]);
  $this->actingAs($outsider,'sanctum')->getJson('/api/v1/documents',$this->headers)->assertOk()->assertJsonMissing(['id'=>$doc->id]);
  $this->actingAs($this->member,'sanctum')->deleteJson('/api/v1/documents/'.$doc->id,[],$this->headers)->assertForbidden();
 }
 public function test_group_creator_can_replace_a_document_they_still_control():void {
  $doc=$this->document($this->owner,'group',$this->group->id);
  $this->actingAs($this->owner,'sanctum')->putJson('/api/v1/documents/'.$doc->id,['file'=>UploadedFile::fake()->createWithContent('replacement.txt','replacement')],$this->headers)->assertStatus(202);
  $this->assertDatabaseHas('documents',['id'=>$doc->id,'version'=>2,'status'=>'queued']);
 }
}
