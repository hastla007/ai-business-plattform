<?php
namespace App\Http\Middleware;
use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
class ResolveOrganization {
 public function handle(Request $request, Closure $next) {
  $id=$request->header('X-Organization-Id');
  if(!$id) return response()->json(['error'=>'organization_required'],400);
  $membership=Membership::where('organization_id',$id)->where('user_id',$request->user()->id)->first();
  if(!$membership) return response()->json(['error'=>'organization_forbidden'],403);
  $request->attributes->set('organization_id',(int)$id);
  $request->attributes->set('organization_role',$membership->role);
  return $next($request);
 }
}