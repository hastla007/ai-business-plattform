<?php
namespace App\Services;
use App\Models\ModelProfile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class KnowledgeRetriever {
 public function __construct(private ModelProvider $provider){}
 public function search(string $q,int $org,int $user,int $limit=5,?array $allowedSourceIds=null):array {
  $p=ModelProfile::where('organization_id',$org)->where('is_active',true)->first();
  if(!$p)return [];
  $expected=(int)$p->embedding_dimensions;if($expected!==1536)throw new RuntimeException('The current vector index supports exactly 1536 dimensions.');$embedding=$this->provider->embed([$q],$p,$user)[0]??null;
  if(!is_array($embedding)||count($embedding)!==$expected)throw new RuntimeException('Embedding dimension does not match the configured vector index.');
  $v='['.implode(',',array_map(fn($x)=>(string)(float)$x,$embedding)).']';
  $scope="c.organization_id = ? AND ((d.access_scope = 'organization') OR (d.access_scope = 'owner' AND d.created_by = ?) OR (d.access_scope = 'group' AND EXISTS (SELECT 1 FROM group_memberships gm WHERE gm.organization_id = c.organization_id AND gm.group_id = d.group_id AND gm.user_id = ?)))";
  $sourceFilter='';$sourceBindings=[];
  if(is_array($allowedSourceIds)&&count($allowedSourceIds)){
   $sourceFilter=' AND d.knowledge_source_id IN ('.implode(',',array_fill(0,count($allowedSourceIds),'?')).')';
   $sourceBindings=array_map('intval',$allowedSourceIds);
  }
  $semantic=DB::select("SELECT c.id,c.document_id,c.content,c.source_locator,d.name AS document_name,(c.embedding <=> ?::vector) AS distance FROM document_chunks c JOIN documents d ON d.id=c.document_id WHERE $scope $sourceFilter AND d.status = 'indexed' AND d.embedding_model = ? AND c.embedding IS NOT NULL AND vector_dims(c.embedding) = 1536 ORDER BY c.embedding <=> ?::vector LIMIT ?",array_merge([$v,$org,$user,$user],$sourceBindings,[$p->embedding_model,$v,$limit*3]));
  $keyword=DB::select("SELECT c.id,c.document_id,c.content,c.source_locator,d.name AS document_name,1.0 AS distance FROM document_chunks c JOIN documents d ON d.id=c.document_id WHERE $scope $sourceFilter AND d.status = 'indexed' AND d.embedding_model = ? AND to_tsvector('simple',c.content) @@ plainto_tsquery('simple',?) LIMIT ?",array_merge([$org,$user,$user],$sourceBindings,[$p->embedding_model,$q,$limit*3]));
  return collect($semantic)->concat($keyword)->unique('id')->take($limit)->map(fn($r)=>['document_id'=>$r->document_id,'document'=>$r->document_name,'locator'=>$r->source_locator,'content'=>$r->content])->values()->all();
 }
}
