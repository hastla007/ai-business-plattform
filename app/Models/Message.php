<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Message extends Model { protected $fillable=['organization_id','conversation_id','role','content','metadata']; protected function casts(): array{return ['metadata'=>'array'];} }