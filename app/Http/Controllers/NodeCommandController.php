<?php
namespace App\Http\Controllers;
use App\Models\NodeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class NodeCommandController extends Controller {
 public function poll(Request $r){
  $node=$r->attributes->get('node');
  NodeCommand::where('node_id',$node->id)->whereIn('status',['queued','running'])->where('expires_at','<=',now())->update(['status'=>'expired']);
  $commands=DB::transaction(function()use($node){
   $running=NodeCommand::where('node_id',$node->id)->where('status','running')->where('expires_at','>',now())->count();
   $capacity=max(0,(int)$node->max_parallel-$running);
   $items=NodeCommand::where('node_id',$node->id)->where('status','queued')->where('expires_at','>',now())->orderBy('id')->limit($capacity)->lockForUpdate()->get();
   foreach($items as $item)$item->update(['status'=>'running']);
   return $items;
  });
  return response()->json(['commands'=>$commands->map(fn($c)=>['id'=>$c->id,'payload'=>$c->payload])]);
 }
 public function result(Request $r,NodeCommand $command){
  $node=$r->attributes->get('node');
  abort_unless((int)$command->node_id===(int)$node->id&&(int)$command->organization_id===(int)$node->organization_id,404);
  $d=$r->validate(['result'=>'required|array','failed'=>'required|boolean']);
  if($command->status!=='running'||$command->expires_at->isPast())return response()->json(['error'=>'command_expired'],409);
  $command->update(['status'=>$d['failed']?'failed':'completed','result'=>$d['result']]);
  return response()->noContent();
 }
}