<?php
namespace App\Http\Controllers;
use App\Models\{ModelProfile,Node};
use Illuminate\Http\Request;
class ModelProfileController extends Controller {
 public function index(Request $r){return ModelProfile::where('organization_id',$r->attributes->get('organization_id'))->with('node:id,name,status,last_seen_at')->orderByDesc('is_active')->get();}
 public function store(Request $r){
  $org=(int)$r->attributes->get('organization_id');$d=$r->validate(['name'=>'required|string|max:120','model'=>'required|string|max:120','embedding_model'=>'required|string|max:120','base_url'=>'required|url|max:500','node_id'=>'nullable|integer','options'=>'nullable|array','is_active'=>'sometimes|boolean']);
  if(!empty($d['node_id']))Node::where('organization_id',$org)->whereKey($d['node_id'])->firstOrFail();
  $d['organization_id']=$org;$d['provider_key']='openai-compatible';$d['is_active']=$d['is_active']??false;
  if($d['is_active'])ModelProfile::where('organization_id',$org)->update(['is_active'=>false]);
  return response()->json(ModelProfile::create($d),201);
 }
 public function update(Request $r,ModelProfile $profile){
  $org=(int)$r->attributes->get('organization_id');
  abort_unless((int)$profile->organization_id===$org,404);
  $d=$r->validate(['name'=>'sometimes|required|string|max:120','model'=>'sometimes|required|string|max:120','embedding_model'=>'sometimes|required|string|max:120','base_url'=>'sometimes|required|url|max:500','node_id'=>'nullable|integer','options'=>'nullable|array','is_active'=>'sometimes|boolean']);
  if(!empty($d['node_id']))Node::where('organization_id',$org)->whereKey($d['node_id'])->firstOrFail();
  if(($d['is_active']??false)===true)ModelProfile::where('organization_id',$org)->update(['is_active'=>false]);
  $profile->update($d);
  return $profile->fresh()->load('node:id,name,status,last_seen_at');
 }
}