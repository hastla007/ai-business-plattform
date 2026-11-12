<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assistant extends Model {
 protected $fillable=['organization_id','name','slug','access_mode','is_active'];
 protected function casts(): array{return ['is_active'=>'boolean'];}
 public function versions(){return $this->hasMany(AssistantVersion::class);}
 public function accessRules(){return $this->hasMany(AssistantAccessRule::class);}
}