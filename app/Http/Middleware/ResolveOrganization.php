<?php
namespace App\Http\Middleware;
use App\Models\{Membership,Organization};
use Closure;
use Illuminate\Http\Request;
class ResolveOrganization {
 public function handle(Request $request, Closure $next) {
  $id=$request->header('X-Organization-Id');
  if(!$id)return response()->json(['error'=>'organization_required'],400);
  $membership=Membership::where('organization_id',$id)->where('user_id',$request->user()->id)->first();
  if(!$membership)return response()->json(['error'=>'organization_forbidden'],403);
  $request->attributes->set('organization_id',(int)$id);$request->attributes->set('organization_role',$membership->role);
  $settings=Organization::findOrFail((int)$id)->settings??[];$mode=config('services.auth.deployment_mode','onprem');
  if(in_array($mode,['saas','hybrid'],true)){
   $status=$settings['license_status']??'active';$expires=$settings['plan_expires_at']??null;
   $expiry=$expires?strtotime((string)$expires):null;$graceDays=max(0,(int)($settings['plan_grace_days']??7));
   $graceEnd=$expiry===null?null:$expiry+$graceDays*86400;
   $locked=$status==='suspended'||($status==='expired'&&($graceEnd===null||time()>$graceEnd))||($expiry!==null&&time()>$graceEnd);
   $renewalRoute=$request->path()==='api/v1/organization/settings'&&$request->isMethod('put')&&in_array($membership->role,['owner','admin'],true);
   $readOnly=in_array($request->method(),['GET','HEAD','OPTIONS'],true);
   if($locked&&!$readOnly&&!$renewalRoute&&$request->path()!=='api/v1/logout')return response()->json(['error'=>'plan_expired','message'=>'Der SaaS-Plan ist abgelaufen oder gesperrt. Bitte verlängere ihn in den Organisationseinstellungen.'],402);
  }
  return $next($request);
 }
}
