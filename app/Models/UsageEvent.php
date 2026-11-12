<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UsageEvent extends Model {
 public $timestamps=false;
 protected $fillable=['organization_id','user_id','model_profile_id','agent_run_id','request_id','input_tokens','output_tokens','cost_micros','status','created_at'];
 protected function casts():array{return ['created_at'=>'datetime','input_tokens'=>'integer','output_tokens'=>'integer','cost_micros'=>'integer'];}
}
