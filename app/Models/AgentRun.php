<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AgentRun extends Model {
 protected $fillable=['organization_id','user_id','assistant_version_id','status','request_id','input','output','error_code','model_profile_id','started_at','completed_at'];
 protected function casts(): array{return ['started_at'=>'datetime','completed_at'=>'datetime'];}
 public function events(){return $this->hasMany(AgentRunEvent::class);}
 public function modelProfile(){return $this->belongsTo(ModelProfile::class);}
}