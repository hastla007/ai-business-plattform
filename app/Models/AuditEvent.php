<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditEvent extends Model { public $timestamps=false;protected $fillable=['organization_id','user_id','action','request_id','metadata','created_at'];protected function casts():array{return ['metadata'=>'array','created_at'=>'datetime'];} }