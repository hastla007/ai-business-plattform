<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentChunk extends Model { protected $fillable=['organization_id','document_id','owner_user_id','access_scope','content','source_locator']; }