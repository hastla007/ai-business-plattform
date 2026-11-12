<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assistant extends Model {
 protected $fillable=['organization_id','name','slug','is_active'];
 protected function casts(): array{return ['is_active'=>'boolean'];}
 public function versions(){return $this->hasMany(AssistantVersion::class);}
}