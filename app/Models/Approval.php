<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Approval extends Model {
 protected $fillable=['organization_id','agent_run_id','user_id','approved_by','action_key','action_payload','payload_hash','idempotency_key','action_result','status','expires_at','executed_at'];
 protected function casts(): array { return ['action_payload'=>'array','action_result'=>'array','expires_at'=>'datetime','executed_at'=>'datetime']; }
 public function run(){return $this->belongsTo(AgentRun::class,'agent_run_id');}
 public function requester(){return $this->belongsTo(User::class,'user_id');}
 public function approver(){return $this->belongsTo(User::class,'approved_by');}
}