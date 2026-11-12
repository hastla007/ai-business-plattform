<?php
namespace App\Console\Commands;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class PurgeExpiredData extends Command {
 protected $signature='engine:purge-retained-data';
 protected $description='Delete expired conversation, agent-run and audit data under each organization retention policy';
 public function handle():int {
  Organization::query()->select(['id','settings'])->chunkById(100,function($organizations){
   foreach($organizations as $org){
    $days=(int)(($org->settings??[])['data_retention_days']??365);
    if($days<7||$days>3650)continue;
    $cutoff=now()->subDays($days);
    DB::transaction(function()use($org,$cutoff){
     DB::table('conversations')->where('organization_id',$org->id)->where('created_at','<',$cutoff)->delete();
     DB::table('agent_runs')->where('organization_id',$org->id)->where('created_at','<',$cutoff)->delete();
     DB::table('audit_events')->where('organization_id',$org->id)->where('created_at','<',$cutoff)->delete();
    });
   }
  });
  $this->info('Retention cleanup completed.');
  return self::SUCCESS;
 }
}