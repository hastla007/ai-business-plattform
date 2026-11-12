<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssistantVersion extends Model {
 protected $fillable=['assistant_id','version','purpose','system_instructions','allowed_models','allowed_knowledge_sources','allowed_tools','max_steps','timeout_seconds','budget_limit','approval_policy','retention_policy','is_published'];
 protected function casts(): array{return ['allowed_models'=>'array','allowed_knowledge_sources'=>'array','allowed_tools'=>'array','is_published'=>'boolean'];}
}