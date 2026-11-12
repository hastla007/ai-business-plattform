<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Node extends Model {
 protected $fillable=['organization_id','site_id','name','status','capabilities','last_seen_at','version'];
 protected function casts():array{return ['capabilities'=>'array','last_seen_at'=>'datetime'];}
}