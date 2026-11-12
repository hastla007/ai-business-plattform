<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
class RequestContext {
 private function runId(Request $request):?int{$run=$request->route('run');if(is_object($run)&&isset($run->id))return (int)$run->id;if(is_numeric($run))return (int)$run;return null;}
 public function handle(Request $request,Closure $next){
  $candidate=(string)$request->header('X-Request-ID','');
  $id=preg_match('/^[A-Za-z0-9._-]{8,80}$/',$candidate)?$candidate:(string)Str::uuid();
  $request->attributes->set('request_id',$id);$started=microtime(true);
  $response=$next($request);$response->headers->set('X-Request-ID',$id);
  Log::info('http.request',['request_id'=>$id,'method'=>$request->method(),'path'=>$request->path(),'status'=>$response->getStatusCode(),'runtime_ms'=>(int)((microtime(true)-$started)*1000),'organization_id'=>$request->attributes->get('organization_id'),'agent_run_id'=>$this->runId($request)]);
  return $response;
 }
}
