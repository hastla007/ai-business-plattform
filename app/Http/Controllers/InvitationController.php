<?php
namespace App\Http\Controllers;
use App\Models\{Membership,Organization,OrganizationInvitation,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class InvitationController extends Controller {
 public function store(Request $r){
  $org=(int)$r->attributes->get('organization_id');$d=$r->validate(['email'=>'required|email|max:255','role'=>'nullable|in:admin,member']);
  $email=strtolower($d['email']);$settings=Organization::findOrFail($org)->settings??[];$limit=(int)($settings['max_users']??0);if($limit>0){$members=Membership::where('organization_id',$org)->count();$pending=OrganizationInvitation::where('organization_id',$org)->whereNull('accepted_at')->where('expires_at','>',now())->count();if($members+$pending>=$limit)abort(402,'Organization seat limit reached.');}
  $token=Str::random(64);$invitation=OrganizationInvitation::create(['organization_id'=>$org,'email'=>$email,'role'=>$d['role']??'member','token_hash'=>hash('sha256',$token),'invited_by'=>$r->user()->id,'expires_at'=>now()->addDays(7)]);
  return response()->json(['invitation_id'=>$invitation->id,'email'=>$email,'token'=>$token,'expires_at'=>$invitation->expires_at,'notice'=>'Share this token securely; it is returned only once.'],201);
 }
 public function register(Request $r){
  $d=$r->validate(['token'=>'required|string|min:32|max:100','name'=>'required|string|max:120','password'=>'required|string|min:12']);
  $inv=OrganizationInvitation::where('token_hash',hash('sha256',$d['token']))->whereNull('accepted_at')->where('expires_at','>',now())->firstOrFail();
  if(User::where('email',$inv->email)->exists())throw ValidationException::withMessages(['token'=>['An account for this invitation already exists. Sign in and accept it instead.']]);
  [$user,$org]=DB::transaction(function()use($inv,$d){
   $locked=OrganizationInvitation::whereKey($inv->id)->lockForUpdate()->firstOrFail();
   abort_if($locked->accepted_at||$locked->expires_at->isPast(),410,'Invitation is expired or already used.');
   $user=User::create(['name'=>$d['name'],'email'=>$locked->email,'password'=>Hash::make($d['password'])]);
   Membership::create(['organization_id'=>$locked->organization_id,'user_id'=>$user->id,'role'=>$locked->role]);
   $locked->update(['accepted_at'=>now()]);
   return [$user,Organization::findOrFail($locked->organization_id)];
  });
  if($r->hasSession()){
   \Illuminate\Support\Facades\Auth::guard('web')->login($user);$r->session()->regenerate();
   return response()->json(['user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email],'organization'=>['id'=>$org->id,'name'=>$org->name]],201);
  }
  return response()->json(['token'=>$user->createToken('api')->plainTextToken,'organization'=>['id'=>$org->id,'name'=>$org->name]],201);
 }
 public function accept(Request $r){
  $d=$r->validate(['token'=>'required|string|min:32|max:100']);$user=$r->user();
  $inv=OrganizationInvitation::where('token_hash',hash('sha256',$d['token']))->whereNull('accepted_at')->where('expires_at','>',now())->firstOrFail();
  abort_unless(strtolower($user->email)===strtolower($inv->email),403);
  Membership::firstOrCreate(['organization_id'=>$inv->organization_id,'user_id'=>$user->id],['role'=>$inv->role]);
  $inv->update(['accepted_at'=>now()]);
  return response()->json(['organization_id'=>$inv->organization_id,'role'=>$inv->role],201);
 }
}
