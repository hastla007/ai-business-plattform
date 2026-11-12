<?php
namespace App\Http\Controllers;
use App\Models\Organization;
use Illuminate\Http\Request;
class OrganizationSettingsController extends Controller {
 public function show(Request $r){return response()->json(['settings'=>Organization::findOrFail($r->attributes->get('organization_id'))->settings??[]]);}
 public function update(Request $r){
  $d=$r->validate(['deployment_mode'=>'sometimes|in:onprem,saas,hybrid','allow_cloud_fallback'=>'sometimes|boolean','data_retention_days'=>'sometimes|integer|min:7|max:3650','allowed_data_classes'=>'sometimes|array','allowed_data_classes.*'=>'in:public,internal,confidential']);
  $org=Organization::findOrFail($r->attributes->get('organization_id'));$org->settings=array_merge($org->settings??[],$d);$org->save();
  return response()->json(['settings'=>$org->settings]);
 }
}