<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ModelProfile extends Model {
 protected $fillable=['organization_id','name','provider_key','model','embedding_model','base_url','is_active','options'];
 protected function casts(): array{return ['is_active'=>'boolean','options'=>'array'];}
}