<?php
namespace App\Http\Controllers;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class OperationalMetricsController extends Controller {
 public function __invoke(Request $r){
  $org=(int)$r->attributes->get('organization_id');$since=now()->subDay();
  return response()->json(['window'=>'24h','chat_requests'=>DB::table('audit_events')->where('organization_id',$org)->where('action','chat.completed')->where('created_at','>=',$since)->count(),'agent_runs'=>DB::table('agent_runs')->where('organization_id',$org)->where('created_at','>=',$since)->count(),'pending_approvals'=>DB::table('approvals')->where('organization_id',$org)->where('status','pending')->count(),'online_nodes'=>Node::where('organization_id',$org)->where('status','online')->where('last_seen_at','>=',now()->subMinutes(2))->count(),'recorded_tokens'=>DB::table('usage_events')->where('organization_id',$org)->where('created_at','>=',$since)->sum(DB::raw('input_tokens + output_tokens'))]);
 }
}
