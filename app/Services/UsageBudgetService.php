<?php
namespace App\Services;
use App\Models\{AuditEvent,ModelProfile,Organization,UsageEvent};
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
class UsageBudgetService {
 public function reserve(int $org,?int $user,ModelProfile $profile,string $requestId,array $messages,?int $runId=null,string $kind='chat'):array {
  $options=$profile->options??[];$settings=Organization::findOrFail($org)->settings??[];
  $estimatedInput=max(1,(int)ceil(collect($messages)->sum(fn($m)=>mb_strlen((string)($m['content']??'')))/4));
  $maxOutput=$kind==='embedding'?0:max(64,min((int)($options['max_output_tokens']??1024),8192));
  $inputRateKey=$kind==='embedding'?'cost_micros_per_1k_embedding':'cost_micros_per_1k_input';
  $rateIn=max(0,(int)($options[$inputRateKey]??$options['cost_micros_per_1k_input']??0));
  $rateOut=$kind==='embedding'?0:max(0,(int)($options['cost_micros_per_1k_output']??0));
  $reserveCost=(int)ceil($estimatedInput*$rateIn/1000+$maxOutput*$rateOut/1000);
  try{return DB::transaction(function()use($org,$user,$profile,$requestId,$runId,$estimatedInput,$maxOutput,$reserveCost,$settings,$options,$rateIn,$rateOut){
   Organization::whereKey($org)->lockForUpdate()->firstOrFail();$day=now()->startOfDay();$month=now()->startOfMonth();
   $used=UsageEvent::where('organization_id',$org)->where('created_at','>=',$day)->sum(DB::raw('input_tokens + output_tokens'));
   $daily=(int)($settings['daily_token_limit']??0);if($daily>0&&$used+$estimatedInput+$maxOutput>$daily)throw new HttpException(429,'Organization daily token budget reached.');
   $userLimit=(int)($settings['daily_token_limit_per_user']??0);
   if($user!==null&&$userLimit>0){$userUsed=UsageEvent::where('organization_id',$org)->where('user_id',$user)->where('created_at','>=',$day)->sum(DB::raw('input_tokens + output_tokens'));if($userUsed+$estimatedInput+$maxOutput>$userLimit)throw new HttpException(429,'User daily token budget reached.');}
   $profileLimit=(int)($options['daily_token_limit']??0);
   if($profileLimit>0){$profileUsed=UsageEvent::where('organization_id',$org)->where('model_profile_id',$profile->id)->where('created_at','>=',$day)->sum(DB::raw('input_tokens + output_tokens'));if($profileUsed+$estimatedInput+$maxOutput>$profileLimit)throw new HttpException(429,'Model profile daily token budget reached.');}
   $monthCost=UsageEvent::where('organization_id',$org)->where('created_at','>=',$month)->sum('cost_micros');
   $monthly=(int)($settings['monthly_cost_limit_micros']??0);if($monthly>0&&$monthCost+$reserveCost>$monthly)throw new HttpException(429,'Organization monthly cost budget reached.');
   $profileMonthly=(int)($options['monthly_cost_limit_micros']??0);
   if($profileMonthly>0){$profileCost=UsageEvent::where('organization_id',$org)->where('model_profile_id',$profile->id)->where('created_at','>=',$month)->sum('cost_micros');if($profileCost+$reserveCost>$profileMonthly)throw new HttpException(429,'Model profile monthly cost budget reached.');}
   $event=UsageEvent::create(['organization_id'=>$org,'user_id'=>$user,'model_profile_id'=>$profile->id,'agent_run_id'=>$runId,'request_id'=>$requestId,'input_tokens'=>$estimatedInput,'output_tokens'=>$maxOutput,'cost_micros'=>$reserveCost,'status'=>'pending','created_at'=>now()]);
   return ['event'=>$event,'max_output_tokens'=>$maxOutput,'rate_in'=>$rateIn,'rate_out'=>$rateOut];
  });}catch(HttpException $e){AuditEvent::create(['organization_id'=>$org,'user_id'=>$user,'action'=>'usage.rejected','request_id'=>$requestId,'metadata'=>['profile_id'=>$profile->id,'run_id'=>$runId,'kind'=>$kind,'status'=>$e->getStatusCode(),'reason'=>mb_substr($e->getMessage(),0,160),'created_at'=>now()]]);throw $e;}
 }
 public function reserveEmbedding(ModelProfile $profile,string $requestId,array $texts,?int $userId=null):array {
  return $this->reserve((int)$profile->organization_id,$userId,$profile,$requestId,[['role'=>'user','content'=>implode(PHP_EOL,$texts)]],null,'embedding');
 }
 public function complete(UsageEvent $event,array $usage,array $reservation,bool $failed=false):void {
  $input=max(0,(int)($usage['prompt_tokens']??$usage['input_tokens']??$event->input_tokens));
  $output=$failed?0:max(0,(int)($usage['completion_tokens']??$usage['output_tokens']??0));
  $cost=(int)ceil($input*$reservation['rate_in']/1000+$output*$reservation['rate_out']/1000);
  $event->update(['input_tokens'=>$input,'output_tokens'=>$output,'cost_micros'=>$cost,'status'=>$failed?'failed':'completed']);
 }
}
