<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ModelProfile extends Model {
 protected $fillable=['organization_id','node_id','name','provider_key','model','embedding_dimensions','embedding_model','base_url','is_active','options'];
 protected function casts(): array{return ['is_active'=>'boolean','options'=>'array','embedding_dimensions'=>'integer'];}
 public function organization(){return $this->belongsTo(Organization::class);}
 public function node(){return $this->belongsTo(Node::class);}
}