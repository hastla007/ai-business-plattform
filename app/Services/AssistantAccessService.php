<?php
namespace App\Services;
use App\Models\{Assistant,AssistantAccessRule};
use Illuminate\Support\Facades\DB;
class AssistantAccessService {
 public function allows(Assistant $assistant,int $userId,int $organizationId):bool {
  if((int)$assistant->organization_id!==$organizationId||!$assistant->is_active)return false;
  $rules=AssistantAccessRule::where('organization_id',$organizationId)->where('assistant_id',$assistant->id);
  if(!$rules->exists())return $assistant->access_mode!=='restricted';
  if($rules->where('subject_type','user')->where('subject_id',$userId)->exists())return true;
  return DB::table('assistant_access_rules as ar')
   ->join('group_memberships as gm',function($join){$join->on('gm.group_id','=','ar.subject_id')->on('gm.organization_id','=','ar.organization_id');})
   ->where('ar.organization_id',$organizationId)->where('ar.assistant_id',$assistant->id)->where('ar.subject_type','group')->where('gm.user_id',$userId)->exists();
 }
}
