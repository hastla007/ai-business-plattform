<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class RequireOrganizationRole {
 public function handle(Request $request,Closure $next,...$roles) {
  if(!in_array($request->attributes->get('organization_role'),$roles,true)) return response()->json(['error'=>'role_forbidden'],403);
  return $next($request);
 }
}