<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Tool extends Model {
 protected $fillable=['organization_id','name','input_schema','is_read_only','is_active'];
 protected function casts():array{return ['input_schema'=>'array','is_read_only'=>'boolean','is_active'=>'boolean'];}
}