<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Group extends Model {
 protected $fillable=['organization_id','name'];
 public function members(){return $this->belongsToMany(User::class,'group_memberships')->withTimestamps();}
}