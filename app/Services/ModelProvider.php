<?php
namespace App\Services;
use App\Models\ModelProfile;
interface ModelProvider {
 public function chat(array $messages,ModelProfile $profile):array;
 public function embed(array $texts,ModelProfile $profile,?int $userId=null):array;
 public function streamChat(array $messages,ModelProfile $profile):iterable;
}
