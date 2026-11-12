<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class ToolRegistry {
 public function validate(string $name,array $arguments,array $allowed): array {
  if(!in_array($name,$allowed,true)||$name!=='search_knowledge') throw ValidationException::withMessages(['tool'=>['Tool is not enabled for this agent.']]);
  $query=$arguments['query']??null;
  if(!is_string($query)||trim($query)===''||mb_strlen($query)>12000) throw ValidationException::withMessages(['arguments.query'=>['A non-empty query up to 12000 characters is required.']]);
  return ['query'=>trim($query)];
 }
}