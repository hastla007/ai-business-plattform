<?php
namespace Tests\Feature;
use App\Models\{Document,Group,Membership,Organization,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class DocumentAuthorizationTest extends TestCase {
 use RefreshDatabase;
 public function test_group_read_access_does_not_let_another_member_delete_a_shared_document():void {
  $owner=User::create(['name'=>'Owner','email'=>'doc-owner@example.test','password'=>'a-secure-password']);
  $member=User::create(['name'=>'Member','email'=>'doc-member@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Docs','slug'=>'docs-org','settings'=>[]]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$owner->id,'role'=>'member']);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$member->id,'role'=>'member']);
  $group=Group::create(['organization_id'=>$org->id,'name'=>'Finance']);
  DB::table('group_memberships')->insert([['organization_id'=>$org->id,'group_id'=>$group->id,'user_id'=>$owner->id,'created_at'=>now(),'updated_at'=>now()],['organization_id'=>$org->id,'group_id'=>$group->id,'user_id'=>$member->id,'created_at'=>now(),'updated_at'=>now()]]);
  $doc=Document::create(['organization_id'=>$org->id,'created_by'=>$owner->id,'group_id'=>$group->id,'name'=>'budget.txt','disk'=>'local','path'=>'org/budget.txt','content_hash'=>str_repeat('a',64),'version'=>1,'classification'=>'internal','access_scope'=>'group','status'=>'indexed','size'=>10]);
  $this->actingAs($member,'sanctum')->getJson('/api/v1/documents',['X-Organization-Id'=>(string)$org->id])->assertOk()->assertJsonFragment(['id'=>$doc->id]);
  $this->deleteJson('/api/v1/documents/'.$doc->id,[],['X-Organization-Id'=>(string)$org->id])->assertForbidden();
  $this->assertDatabaseHas('documents',['id'=>$doc->id]);
 }
}
