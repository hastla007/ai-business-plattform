<?php
namespace Tests\Feature;
use App\Models\{AgentRun,Approval,Assistant,AssistantVersion,Conversation,Document,Group,Membership,ModelProfile,Node,Organization,Ticket,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class TenantResourceIsolationTest extends TestCase {
 use RefreshDatabase;
 private User $owner;
 private User $foreignOwner;
 private Organization $org;
 private Organization $foreignOrg;
 private array $headers;
 private Conversation $foreignConversation;
 private AgentRun $foreignRun;
 private Document $foreignDocument;
 private Group $foreignGroup;
 private Assistant $foreignAssistant;
 private ModelProfile $foreignProfile;
 private Node $foreignNode;
 private Approval $foreignApproval;
 private Ticket $foreignTicket;
 protected function setUp():void {
  parent::setUp();
  $this->owner=User::create(['name'=>'Org A owner','email'=>'isolation-a@example.test','password'=>'a-secure-password']);
  $this->foreignOwner=User::create(['name'=>'Org B owner','email'=>'isolation-b@example.test','password'=>'a-secure-password']);
  $this->org=Organization::create(['name'=>'Organization A','slug'=>'isolation-a','settings'=>[]]);
  $this->foreignOrg=Organization::create(['name'=>'Organization B','slug'=>'isolation-b','settings'=>[]]);
  Membership::create(['organization_id'=>$this->org->id,'user_id'=>$this->owner->id,'role'=>'owner']);
  Membership::create(['organization_id'=>$this->foreignOrg->id,'user_id'=>$this->foreignOwner->id,'role'=>'owner']);
  $this->headers=['X-Organization-Id'=>(string)$this->org->id];
  $this->foreignGroup=Group::create(['organization_id'=>$this->foreignOrg->id,'name'=>'Foreign group']);
  $this->foreignAssistant=Assistant::create(['organization_id'=>$this->foreignOrg->id,'name'=>'Foreign assistant','slug'=>'foreign-assistant','is_active'=>true]);
  $version=AssistantVersion::create(['assistant_id'=>$this->foreignAssistant->id,'version'=>1,'purpose'=>'Test','system_instructions'=>'Test','allowed_models'=>[],'allowed_knowledge_sources'=>[],'allowed_tools'=>['search_knowledge'],'max_steps'=>3,'timeout_seconds'=>60,'approval_policy'=>'never_write','retention_policy'=>'organization_default','is_published'=>true]);
  $profile=ModelProfile::create(['organization_id'=>$this->foreignOrg->id,'name'=>'Foreign profile','provider_key'=>'openai-compatible','model'=>'test-chat','embedding_model'=>'test-embed','base_url'=>'http://localhost:8080','is_active'=>true]);
  $this->foreignConversation=Conversation::create(['organization_id'=>$this->foreignOrg->id,'user_id'=>$this->foreignOwner->id,'title'=>'Foreign history']);
  $this->foreignRun=AgentRun::create(['organization_id'=>$this->foreignOrg->id,'user_id'=>$this->foreignOwner->id,'assistant_version_id'=>$version->id,'model_profile_id'=>$profile->id,'status'=>'completed','request_id'=>(string)Str::uuid(),'input'=>'foreign run']);
  $this->foreignApproval=Approval::create(['organization_id'=>$this->foreignOrg->id,'agent_run_id'=>$this->foreignRun->id,'user_id'=>$this->foreignOwner->id,'action_key'=>'create_ticket','action_payload'=>['title'=>'Foreign','description'=>'Foreign ticket','priority'=>'normal'],'payload_hash'=>str_repeat('b',64),'idempotency_key'=>(string)Str::uuid(),'status'=>'pending','expires_at'=>now()->addMinutes(15)]);
  $this->foreignTicket=Ticket::create(['organization_id'=>$this->foreignOrg->id,'approval_id'=>$this->foreignApproval->id,'created_by'=>$this->foreignOwner->id,'title'=>'Foreign ticket','description'=>'Private','priority'=>'normal','status'=>'open']);
  $this->foreignDocument=Document::create(['organization_id'=>$this->foreignOrg->id,'created_by'=>$this->foreignOwner->id,'name'=>'foreign.txt','disk'=>'local','path'=>'organizations/foreign.txt','content_hash'=>str_repeat('a',64),'version'=>1,'classification'=>'internal','access_scope'=>'organization','status'=>'indexed','size'=>8]);
  $this->foreignProfile=$profile;
  $this->foreignNode=Node::create(['organization_id'=>$this->foreignOrg->id,'name'=>'Foreign node','status'=>'offline','token_hash'=>hash('sha256','foreign-node-token'),'privacy_class'=>'internal','hardware_profile'=>[],'max_parallel'=>1,'capabilities'=>[]]);
 }
 public function test_foreign_conversation_and_agent_run_are_not_visible():void {
  $this->actingAs($this->owner,'sanctum')->getJson('/api/v1/conversations/'.$this->foreignConversation->id,$this->headers)->assertNotFound();
  $this->actingAs($this->owner,'sanctum')->getJson('/api/v1/agent-runs/'.$this->foreignRun->id,$this->headers)->assertNotFound();
  $this->actingAs($this->owner,'sanctum')->getJson('/api/v1/agents',$this->headers)->assertOk()->assertJsonMissing(['id'=>$this->foreignAssistant->id]);
 }
 public function test_foreign_documents_are_not_listed_or_deleted():void {
  $this->actingAs($this->owner,'sanctum')->getJson('/api/v1/documents',$this->headers)->assertOk()->assertJsonMissing(['id'=>$this->foreignDocument->id]);
  $this->actingAs($this->owner,'sanctum')->deleteJson('/api/v1/documents/'.$this->foreignDocument->id,[],$this->headers)->assertNotFound();
 }
 public function test_foreign_groups_are_not_listed_or_changed():void {
  $this->actingAs($this->owner,'sanctum')->getJson('/api/v1/groups',$this->headers)->assertOk()->assertJsonMissing(['id'=>$this->foreignGroup->id]);
  $this->actingAs($this->owner,'sanctum')->putJson('/api/v1/groups/'.$this->foreignGroup->id.'/members/'.$this->owner->id,[],$this->headers)->assertNotFound();
 }
 public function test_foreign_tickets_and_approvals_are_not_listed():void {
  $this->actingAs($this->owner,'sanctum')->getJson('/api/v1/tickets',$this->headers)->assertOk()->assertJsonMissing(['id'=>$this->foreignTicket->id]);
  $this->actingAs($this->owner,'sanctum')->getJson('/api/v1/approvals',$this->headers)->assertOk()->assertJsonMissing(['id'=>$this->foreignApproval->id]);
 }
 public function test_foreign_approval_cannot_be_decided_by_id():void {
  $this->actingAs($this->owner,'sanctum')->postJson('/api/v1/agent-runs/'.$this->foreignRun->id.'/approve',['approval_id'=>$this->foreignApproval->id,'decision'=>'approve'],$this->headers)->assertNotFound();
  $this->assertDatabaseHas('tickets',['id'=>$this->foreignTicket->id,'status'=>'open']);
 }
 public function test_foreign_model_profile_and_node_cannot_be_modified():void {
  $this->actingAs($this->owner,'sanctum')->putJson('/api/v1/model-profiles/'.$this->foreignProfile->id,['name'=>'stolen'],$this->headers)->assertNotFound();
  $this->actingAs($this->owner,'sanctum')->deleteJson('/api/v1/nodes/'.$this->foreignNode->id,[],$this->headers)->assertNotFound();
  $this->assertDatabaseHas('nodes',['id'=>$this->foreignNode->id,'status'=>'offline']);
 }
}
