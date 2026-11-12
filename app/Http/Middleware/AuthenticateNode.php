<?php
namespace App\Http\Middleware;
use App\Models\Node;
use Closure;
use Illuminate\Http\Request;
class AuthenticateNode {
 public function handle(Request $request,Closure $next){
  $token=$request->header('X-Node-Token')?:$request->bearerToken();
  if(!$token)return response()->json(['error'=>'node_auth_required'],401);
  $node=Node::where('token_hash',hash('sha256',$token))->whereNotIn('status',['disabled'])->first();
  if(!$node)return response()->json(['error'=>'node_auth_invalid'],401);
  $request->attributes->set('node',$node);
  return $next($request);
 }
}