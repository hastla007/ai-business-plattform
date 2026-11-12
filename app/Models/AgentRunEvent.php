<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AgentRunEvent extends Model { protected $fillable=['agent_run_id','type','metadata','result_reference']; protected function casts(): array{return ['metadata'=>'array'];} }