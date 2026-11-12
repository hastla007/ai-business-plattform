<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NodeCommand extends Model {
 protected $fillable=['organization_id','node_id','payload','result','status','expires_at'];
 protected function casts(): array{return ['payload'=>'array','result'=>'array','expires_at'=>'datetime'];}
}