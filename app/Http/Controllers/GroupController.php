<?php
namespace App\Http\Controllers;
use App\Models\{Group,Membership,User};
use Illuminate\Http\Request;
class GroupController extends Controller {
 public function index(Request $r){return Group::with('members:id,name,email')->where('organization_id',$r->attributes->get('organization_id'))->get();}
 public function store(Request $r){$d=$r->validate(['name'=>'required|string|max:120']);return Group::create(['organization_id'=>$r->attributes->get('organization_id'),'name'=>$d['name']]);}
 public function addMember(Request $r,Group $group,User $user){
  abort_unless($group->organization_id===$r->attributes->get('organization_id'),404);
  abort_unless(Membership::where('organization_id',$group->organization_id)->where('user_id',$user->id)->exists(),404);
  $group->members()->syncWithoutDetaching([$user->id=>['organization_id'=>$group->organization_id]]);
  return response()->json(['group_id'=>$group->id,'user_id'=>$user->id],201);
 }
 public function removeMember(Request $r,Group $group,User $user){
  abort_unless($group->organization_id===$r->attributes->get('organization_id'),404);
  $group->members()->detach($user->id);
  return response()->noContent();
 }
}