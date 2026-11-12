<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Organization extends Model {
 protected $fillable=['name','slug','settings'];
 protected function casts(): array{return ['settings'=>'array'];}
}