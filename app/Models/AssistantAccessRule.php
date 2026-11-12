<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssistantAccessRule extends Model {
 protected $fillable=['organization_id','assistant_id','subject_type','subject_id','created_by'];
}