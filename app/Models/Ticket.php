<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ticket extends Model {
 protected $fillable=['organization_id','approval_id','created_by','title','description','priority','status'];
}