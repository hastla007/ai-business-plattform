<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrganizationInvitation extends Model {
 protected $fillable=['organization_id','email','role','token_hash','invited_by','expires_at','accepted_at'];
 protected $hidden=['token_hash'];
 protected function casts():array{return ['expires_at'=>'datetime','accepted_at'=>'datetime'];}
}
