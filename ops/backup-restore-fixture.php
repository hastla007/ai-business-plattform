<?php
use App\Models\{Conversation,Document,Membership,Message,ModelProfile,Organization,User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{DB,Hash,Storage};
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$mode=$argv[1]??'';
abort_unless(in_array($mode,['seed','verify'],true),422,'Use seed or verify.');
$slug='backup-restore-fixture';
$org=Organization::where('slug',$slug)->first();
if($mode==='seed'){
 if(!$org)$org=Organization::create(['name'=>'Backup Restore Fixture','slug'=>$slug,'settings'=>['deployment_mode'=>'onprem']]);
 $user=User::firstOrCreate(['email'=>'backup-restore@example.test'],['name'=>'Restore Fixture','password'=>Hash::make('RestoreFixturePassword-2026')]);
 Membership::firstOrCreate(['organization_id'=>$org->id,'user_id'=>$user->id],['role'=>'owner']);
 $profile=ModelProfile::firstOrCreate(['organization_id'=>$org->id,'name'=>'Restore Fixture Model'],['provider_key'=>'openai-compatible','model'=>'fixture-chat','embedding_model'=>'fixture-embedding','embedding_dimensions'=>1536,'base_url'=>'http://127.0.0.1:8080','is_active'=>true]);
 $path='organizations/'.$org->id.'/backup-restore-fixture.txt';$source='Fixture source document for backup and restore.';
 Storage::disk('local')->put($path,$source);
 $doc=Document::updateOrCreate(['organization_id'=>$org->id,'name'=>'backup-restore-fixture.txt'],['created_by'=>$user->id,'disk'=>'local','path'=>$path,'content_hash'=>hash('sha256',$source),'version'=>1,'classification'=>'internal','access_scope'=>'organization','status'=>'indexed','mime_type'=>'text/plain','size'=>strlen($source),'embedding_model'=>$profile->embedding_model]);
 DB::table('document_chunks')->where('document_id',$doc->id)->delete();
 $vector='['.implode(',',array_fill(0,1536,'0.125')).']';
 DB::insert('INSERT INTO document_chunks (organization_id,document_id,owner_user_id,access_scope,content,source_locator,embedding,created_at,updated_at) VALUES (?,?,?,?,?,?,?::vector,now(),now())',[$org->id,$doc->id,$user->id,'organization',$source,'characters 0-48',$vector]);
 $conversation=Conversation::firstOrCreate(['organization_id'=>$org->id,'user_id'=>$user->id,'title'=>'Restore fixture conversation']);
 DB::table('messages')->where('conversation_id',$conversation->id)->delete();
 Message::create(['organization_id'=>$org->id,'conversation_id'=>$conversation->id,'role'=>'user','content'=>'Restore check']);
 Message::create(['organization_id'=>$org->id,'conversation_id'=>$conversation->id,'role'=>'assistant','content'=>'Restored conversation is available.','metadata'=>['sources'=>[['document_id'=>$doc->id,'document'=>$doc->name,'locator'=>'characters 0-48']]]]);
}
$org=Organization::where('slug',$slug)->firstOrFail();$doc=Document::where('organization_id',$org->id)->where('name','backup-restore-fixture.txt')->firstOrFail();
$file=Storage::disk($doc->disk)->get($doc->path);
$chunk=DB::selectOne('SELECT content,vector_dims(embedding) AS dimensions FROM document_chunks WHERE document_id = ? ORDER BY id LIMIT 1',[$doc->id]);
$conversation=Conversation::where('organization_id',$org->id)->where('title','Restore fixture conversation')->firstOrFail();
$messages=Message::where('conversation_id',$conversation->id)->orderBy('id')->get(['role','content','metadata']);
$payload=json_encode(['org'=>$org->slug,'email'=>'backup-restore@example.test','doc_hash'=>$doc->content_hash,'file'=>$file,'chunk'=>$chunk?->content,'dimensions'=>(int)($chunk?->dimensions??0),'messages'=>$messages->toArray()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
if($mode==='verify'&&(!hash_equals($doc->content_hash,hash('sha256',$file))||count($messages)!==2||(int)($chunk?->dimensions??0)!==1536))throw new RuntimeException('Restored database, source file, vector or conversation does not match.');
echo hash('sha256',$payload).PHP_EOL;
