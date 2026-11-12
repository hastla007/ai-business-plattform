<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('about:business-engine',fn()=>$this->info('AI Business Engine API'))->purpose('Show application identity');
Schedule::command('engine:purge-retained-data')->daily();
Schedule::command('engine:expire-approvals')->everyMinute();
