<?php
namespace App\Services;
use App\Models\{Node,NodeCommand};
use RuntimeException;
class PollingNodeTransport implements NodeTransport {
 public function dispatch(Node $node,array $payload):array {
  $timeout=max(5,min((int)config('services.ai.timeout',60),120));
  $command=NodeCommand::create(['organization_id'=>$node->organization_id,'node_id'=>$node->id,'payload'=>$payload,'status'=>'queued','expires_at'=>now()->addSeconds($timeout)]);
  $until=microtime(true)+$timeout;
  do {
   $command->refresh();
   if($command->status==='completed')return $command->result??[];
   if(in_array($command->status,['failed','expired'],true))throw new RuntimeException('Node request failed.');
   usleep(200000);
  } while(microtime(true)<$until);
  NodeCommand::whereKey($command->id)->where('status','queued')->update(['status'=>'expired']);
  throw new RuntimeException('Node request timed out.');
 }
}