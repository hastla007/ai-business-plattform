<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Node extends Model {
 protected $fillable=['organization_id','site_id','name','status','token_hash','privacy_class','hardware_profile','max_parallel','capabilities','last_seen_at','version'];
 protected $hidden=['token_hash'];
 protected function casts(): array{return ['hardware_profile'=>'array','capabilities'=>'array','last_seen_at'=>'datetime'];}
 public function commands(){return $this->hasMany(NodeCommand::class);}
 public function site(){return $this->belongsTo(Site::class);}
}