<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\{Cache,DB};
class HealthController extends Controller {
 public function live(){return response()->json(['status'=>'ok']);}
 public function __invoke(){
  DB::select('SELECT 1');
  $key='health:'.bin2hex(random_bytes(6));Cache::store('redis')->put($key,'ok',10);
  abort_unless(Cache::store('redis')->get($key)==='ok',503);
  return response()->json(['status'=>'ok','checks'=>['database'=>'ok','redis'=>'ok']]);
 }
}