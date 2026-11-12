<?php
namespace App\Http\Controllers;
use App\Models\{Assistant,Membership,ModelProfile,Organization,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
 public function register(Request $request) {
  $d=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:12','organization_name'=>'required|string|max:160']);
  [$u,$o]=DB::transaction(function() use($d){
   $u=User::create(['name'=>$d['name'],'email'=>strtolower($d['email']),'password'=>Hash::make($d['password'])]);
   $o=Organization::create(['name'=>$d['organization_name'],'slug'=>Str::slug($d['organization_name']).'-'.Str::lower(Str::random(5))]);
   Membership::create(['organization_id'=>$o->id,'user_id'=>$u->id,'role'=>'owner']);
   ModelProfile::create(['organization_id'=>$o->id,'name'=>'Lokales Modell','provider_key'=>'openai-compatible','model'=>config('services.ai.chat_model'),'embedding_model'=>config('services.ai.embedding_model'),'base_url'=>config('services.ai.base_url'),'is_active'=>true]);
   $a=Assistant::create(['organization_id'=>$o->id,'name'=>'Wissensagent','slug'=>'knowledge-agent','is_active'=>true]);
   $a->versions()->create(['version'=>1,'purpose'=>'Read-only company knowledge search','system_instructions'=>'Answer in the user language. Use only supplied excerpts for company facts and cite sources. Say when evidence is insufficient.','allowed_models'=>[],'allowed_knowledge_sources'=>[],'allowed_tools'=>['search_knowledge'],'max_steps'=>3,'timeout_seconds'=>60,'approval_policy'=>'never_write','retention_policy'=>'organization_default','is_published'=>true]);
   return [$u,$o];
  });
  return response()->json(['token'=>$u->createToken('api')->plainTextToken,'organization'=>['id'=>$o->id,'name'=>$o->name]],201);
 }
 public function login(Request $request) {
  $d=$request->validate(['email'=>'required|email','password'=>'required|string']);
  $u=User::where('email',strtolower($d['email']))->first();
  if(!$u||!Hash::check($d['password'],$u->password)) throw ValidationException::withMessages(['email'=>['The provided credentials are invalid.']]);
  return response()->json(['token'=>$u->createToken('api')->plainTextToken,'organizations'=>$u->organizations()->select('organizations.id','organizations.name')->get()]);
 }
 public function logout(Request $request){$request->user()->currentAccessToken()?->delete();return response()->noContent();}
}