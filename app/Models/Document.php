<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Document extends Model {
 protected $fillable=['organization_id','knowledge_source_id','created_by','name','disk','path','content_hash','version','classification','access_scope','status','error_message','mime_type','size'];
 public function chunks(){return $this->hasMany(DocumentChunk::class);}
}