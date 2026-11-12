<?php
namespace App\Services;
use App\Models\{ModelProfile,Node};
use RuntimeException;
class ModelRouter {
 public function route(ModelProfile $profile,string $dataClass='internal',?string $requiredModel=null,string $operation='chat',?int $dimensions=null): ?Node {
  $options=$profile->options??[];
  $settings=$profile->organization?->settings??[];
  $requiredNodeId=$profile->node_id ?: ($options['node_id']??null);
  $allowed=$settings['allowed_data_classes']??['public','internal','confidential'];
  if(!in_array($dataClass,$allowed,true))throw new RuntimeException('Organization policy does not permit this data class.');
  $useNodes=(bool)$requiredNodeId||!empty($options['route_to_nodes']);
  if(!$useNodes){
   if(($options['data_location']??'local')==='cloud' && empty($settings['allow_cloud_fallback'])) throw new RuntimeException('Cloud model use is disabled by organization policy.');
   return null;
  }
  $query=Node::query()->where('organization_id',$profile->organization_id)->where('status','online')
   ->whereNotNull('last_seen_at')->where('last_seen_at','>=',now()->subMinutes(2))
   ->where('privacy_class',$dataClass)
   ->whereRaw('(SELECT COUNT(*) FROM node_commands WHERE node_commands.node_id = nodes.id AND node_commands.status IN (?,?) AND node_commands.expires_at > NOW()) < nodes.max_parallel',['queued','running']);
  if($requiredNodeId)$query->whereKey($requiredNodeId);
  if(!empty($options['site_id']))$query->where('site_id',(int)$options['site_id']);
  $capability=$operation==='embedding'?'embedding_models':'models';
  if($operation==='embedding'){$query->whereJsonContains('capabilities->embedding_models',$requiredModel??$profile->embedding_model);if($dimensions!==null)$query->whereJsonContains('capabilities->embedding_dimensions',$dimensions);}
  else $query->where(function($q)use($profile,$requiredModel){$q->whereNull('capabilities')->orWhereJsonContains('capabilities->models',$requiredModel??$profile->model);});
  $node=$query->orderByRaw('(SELECT COUNT(*) FROM node_commands WHERE node_commands.node_id = nodes.id AND node_commands.status IN (?,?) AND node_commands.expires_at > NOW()) ASC',['queued','running'])->first();
  if($node)return $node;
  if(empty($settings['allow_cloud_fallback']))throw new RuntimeException('No eligible online model node is available and cloud fallback is disabled.');
  return null;
 }
}