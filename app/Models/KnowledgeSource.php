<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KnowledgeSource extends Model { protected $fillable=['organization_id','name','description','access_scope']; }