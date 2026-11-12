<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Conversation extends Model {
 protected $fillable=['organization_id','user_id','title','assistant_id','metadata'];
 protected function casts(): array{return ['metadata'=>'array'];}
 public function messages(){return $this->hasMany(Message::class);}
}