<?php
namespace App\Http\Controllers;
use App\Models\{AuditEvent,Organization};
use Illuminate\Http\Request;
class OrganizationSettingsController extends Controller {
 public function show(Request $r){return response()->json(['settings'=>Organization::findOrFail($r->attributes->get('organization_id'))->settings??[]]);}
 public function update(Request $r){
  $d=$r->validate(['deployment_mode'=>'sometimes|in:onprem,saas,hybrid','allow_cloud_fallback'=>'sometimes|boolean','data_retention_days'=>'sometimes|integer|min:7|max:3650','allowed_data_classes'=>'sometimes|array','allowed_data_classes.*'=>'in:public,internal,confidential','daily_token_limit'=>'sometimes|nullable|integer|min:0','daily_token_limit_per_user'=>'sometimes|nullable|integer|min:0','monthly_cost_limit_micros'=>'sometimes|nullable|integer|min:0','max_users'=>'sometimes|nullable|integer|min:1','plan'=>'sometimes|in:trial,standard,enterprise','license_status'=>'sometimes|in:active,grace,expired,suspended','plan_expires_at'=>'sometimes|nullable|date','plan_grace_days'=>'sometimes|integer|min:0|max:90']);
  $org=Organization::findOrFail($r->attributes->get('organization_id'));$before=$org->settings??[];
  if(isset($d['plan'])){$limits=config('services.auth.plans.'.$d['plan'],[]);$d['max_users']=$limits['max_users']??null;$d['monthly_cost_limit_micros']=$limits['monthly_cost_limit_micros']??0;}
  $org->settings=array_merge($before,$d);$org->save();
  $changed=array_intersect_key($d,array_flip(['deployment_mode','plan','license_status','plan_expires_at','plan_grace_days','max_users','monthly_cost_limit_micros']));
  if($changed)AuditEvent::create(['organization_id'=>$org->id,'user_id'=>$r->user()->id,'action'=>'organization.plan.changed','request_id'=>$r->attributes->get('request_id'),'metadata'=>['before'=>array_intersect_key($before,$changed),'after'=>$changed,'created_at'=>now()]]);
  return response()->json(['settings'=>$org->settings]);
 }
}
