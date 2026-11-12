<?php
namespace Tests\Feature;
use App\Models\{Document,Membership,Organization,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class DocumentSourceAuthorizationTest extends TestCase {
 use RefreshDatabase;
 public function test_document_source_is_delivered_to_organization_reader_and_hidden_from_other_tenant():void {
  Storage::fake('local');
  $owner=User::create(['name'=>'Owner','email'=>'source-owner@example.test','password'=>'a-secure-password']);
  $other=User::create(['name'=>'Other','email'=>'source-other@example.test','password'=>'a-secure-password']);
  $org=Organization::create(['name'=>'Source Org','slug'=>'source-org','settings'=>[]]);
  $otherOrg=Organization::create(['name'=>'Other Org','slug'=>'other-source-org','settings'=>[]]);
  Membership::create(['organization_id'=>$org->id,'user_id'=>$owner->id,'role'=>'owner']);
  Membership::create(['organization_id'=>$otherOrg->id,'user_id'=>$other->id,'role'=>'owner']);
  $contents='authorized source fixture';$path='organizations/'.$org->id.'/source.txt';Storage::disk('local')->put($path,$contents);
  $document=Document::create(['organization_id'=>$org->id,'created_by'=>$owner->id,'name'=>'source.txt','disk'=>'local','path'=>$path,'content_hash'=>hash('sha256',$contents),'version'=>1,'classification'=>'internal','access_scope'=>'organization','status'=>'indexed','mime_type'=>'text/plain','size'=>strlen($contents)]);
  $this->actingAs($owner,'sanctum')->get('/api/v1/documents/'.$document->id.'/source',['X-Organization-Id'=>(string)$org->id])->assertOk()->assertSeeText($contents);
  $this->actingAs($other,'sanctum')->getJson('/api/v1/documents/'.$document->id.'/source',['X-Organization-Id'=>(string)$otherOrg->id])->assertNotFound();
 }
}
