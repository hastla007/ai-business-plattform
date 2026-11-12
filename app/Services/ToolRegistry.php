<?php
namespace App\Services;
use App\Models\Tool;
use Illuminate\Validation\ValidationException;
class ToolRegistry {
 public function validate(string $name,array $arguments,array $allowed,int $organizationId):array {
  if(!in_array($name,$allowed,true)||$name!=='search_knowledge') throw ValidationException::withMessages(['tool'=>['Tool is not enabled for this agent.']]);
  $tool=Tool::where('organization_id',$organizationId)->where('name',$name)->where('is_read_only',true)->where('is_active',true)->firstOrFail();
  $schema=$tool->input_schema;
  if(($schema['type']??null)==='object'&&($schema['additionalProperties']??true)===false) {
   foreach(array_keys($arguments) as $key) if(!isset($schema['properties'][$key])) throw ValidationException::withMessages(['arguments'=>['Unknown argument.']]);
  }
  foreach($schema['required']??[] as $key) if(!array_key_exists($key,$arguments)) throw ValidationException::withMessages(['arguments.'.$key=>['Required argument is missing.']]);
  $query=$arguments['query']??null;
  if(!is_string($query)||trim($query)===''||mb_strlen($query)>12000) throw ValidationException::withMessages(['arguments.query'=>['A non-empty query up to 12000 characters is required.']]);
  return ['query'=>trim($query)];
 }
}