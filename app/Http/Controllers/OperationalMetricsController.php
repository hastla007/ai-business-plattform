<?php
namespace App\Http\Controllers;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
class OperationalMetricsController extends Controller {
 public function __invoke(Request $r){
  $org=(int)$r->attributes->get('organization_id');$since=now()->subDay();
  $base=['window'=>'24h','chat_requests'=>DB::table('audit_events')->where('organization_id',$org)->where('action','chat.completed')->where('created_at','>=',$since)->count(),'agent_runs'=>DB::table('agent_runs')->where('organization_id',$org)->where('created_at','>=',$since)->count(),'pending_approvals'=>DB::table('approvals')->where('organization_id',$org)->where('status','pending')->count(),'online_nodes'=>Node::where('organization_id',$org)->where('status','online')->where('last_seen_at','>=',now()->subMinutes(2))->count(),'recorded_tokens'=>DB::table('usage_events')->where('organization_id',$org)->where('created_at','>=',$since)->sum(DB::raw('input_tokens + output_tokens'))];
  $runtime=DB::table('audit_events')->where('organization_id',$org)->where('action','chat.completed')->where('created_at','>=',$since)->limit(1000)->pluck('metadata')->map(fn($m)=>(int)(is_array($m)?($m['runtime_ms']??0):(json_decode($m,true)['runtime_ms']??0)))->filter(fn($n)=>$n>0)->sort()->values();
  $base['chat_latency_p95_ms']=$runtime->isEmpty()?0:$runtime[(int)ceil($runtime->count()*.95)-1];
  $base['failed_agent_runs']=DB::table('agent_runs')->where('organization_id',$org)->where('status','failed')->where('updated_at','>=',$since)->count();
  $base['usage_hourly']=DB::table('usage_events')->leftJoin('model_profiles','model_profiles.id','=','usage_events.model_profile_id')->where('usage_events.organization_id',$org)->where('usage_events.created_at','>=',$since)->selectRaw("date_trunc('hour', usage_events.created_at) AS hour, usage_events.model_profile_id, COALESCE(model_profiles.name, 'Unbekannt') AS profile, SUM(usage_events.input_tokens+usage_events.output_tokens) AS tokens, SUM(usage_events.cost_micros) AS cost_micros")->groupByRaw("date_trunc('hour', usage_events.created_at), usage_events.model_profile_id, model_profiles.name")->orderBy('hour')->get();
  $base['oldest_queue_age_seconds']=Schema::hasTable('jobs')?max(0,now()->timestamp-(int)(DB::table('jobs')->min('created_at')??now()->timestamp)):null;
  if(in_array($r->attributes->get('organization_role'),['owner','admin'],true)){
   $base['usage_by_user']=DB::table('usage_events')->leftJoin('users','users.id','=','usage_events.user_id')->where('usage_events.organization_id',$org)->where('usage_events.created_at','>=',$since)->groupBy('usage_events.user_id','users.name')->selectRaw('usage_events.user_id, COALESCE(users.name, \'Gelöschter Nutzer\') AS user, SUM(usage_events.input_tokens+usage_events.output_tokens) AS tokens, SUM(usage_events.cost_micros) AS cost_micros')->orderByDesc('tokens')->limit(50)->get();
   $base['usage_by_profile']=DB::table('usage_events')->leftJoin('model_profiles','model_profiles.id','=','usage_events.model_profile_id')->where('usage_events.organization_id',$org)->where('usage_events.created_at','>=',$since)->groupBy('usage_events.model_profile_id','model_profiles.name')->selectRaw('usage_events.model_profile_id, COALESCE(model_profiles.name, \'Gelöschtes Profil\') AS profile, SUM(usage_events.input_tokens+usage_events.output_tokens) AS tokens, SUM(usage_events.cost_micros) AS cost_micros')->orderByDesc('tokens')->limit(50)->get();
   $base['rejected_requests']=DB::table('audit_events')->where('organization_id',$org)->where('action','usage.rejected')->where('created_at','>=',$since)->count();
   $base['rejected_by_reason']=DB::table('audit_events')->where('organization_id',$org)->where('action','usage.rejected')->where('created_at','>=',$since)->selectRaw("metadata->>'reason' AS reason, COUNT(*) AS requests")->groupByRaw("metadata->>'reason'")->get();
   $settings=DB::table('organizations')->where('id',$org)->value('settings');if(is_string($settings))$settings=json_decode($settings,true)??[];
   $day=now()->startOfDay();$month=now()->startOfMonth();
   $orgDaily=(int)($settings['daily_token_limit']??0);$userDaily=(int)($settings['daily_token_limit_per_user']??0);$orgMonthly=(int)($settings['monthly_cost_limit_micros']??0);
   $orgUsed=(int)DB::table('usage_events')->where('organization_id',$org)->where('created_at','>=',$day)->sum(DB::raw('input_tokens + output_tokens'));
   $monthlyCost=(int)DB::table('usage_events')->where('organization_id',$org)->where('created_at','>=',$month)->sum('cost_micros');
   $base['remaining_org_daily_tokens']=$orgDaily>0?max(0,$orgDaily-$orgUsed):null;
   $base['remaining_org_monthly_cost_micros']=$orgMonthly>0?max(0,$orgMonthly-$monthlyCost):null;
   $base['remaining_user_daily_tokens']=$userDaily>0?DB::table('usage_events')->leftJoin('users','users.id','=','usage_events.user_id')->where('usage_events.organization_id',$org)->where('usage_events.created_at','>=',$day)->groupBy('usage_events.user_id','users.name')->selectRaw('usage_events.user_id, COALESCE(users.name, \'Gelöschter Nutzer\') AS user, GREATEST(0, ? - SUM(usage_events.input_tokens+usage_events.output_tokens)) AS remaining_tokens',[$userDaily])->get():null;
   $base['remaining_profile_budgets']=DB::table('model_profiles')->where('organization_id',$org)->get(['id','name','options'])->map(function($profile)use($org,$day,$month){
    $options=is_array($profile->options)?$profile->options:(json_decode((string)$profile->options,true)??[]);
    $daily=(int)($options['daily_token_limit']??0);$monthly=(int)($options['monthly_cost_limit_micros']??0);
    $tokens=(int)DB::table('usage_events')->where('organization_id',$org)->where('model_profile_id',$profile->id)->where('created_at','>=',$day)->sum(DB::raw('input_tokens + output_tokens'));
    $cost=(int)DB::table('usage_events')->where('organization_id',$org)->where('model_profile_id',$profile->id)->where('created_at','>=',$month)->sum('cost_micros');
    return ['profile_id'=>$profile->id,'profile'=>$profile->name,'remaining_daily_tokens'=>$daily>0?max(0,$daily-$tokens):null,'remaining_monthly_cost_micros'=>$monthly>0?max(0,$monthly-$cost):null];
   });
  }
  return response()->json($base);
 }
}