<?php
namespace App\Providers;
use App\Services\{ModelProvider,NodeTransport,OpenAiCompatibleProvider,PollingNodeTransport};
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {
  $this->app->bind(ModelProvider::class, OpenAiCompatibleProvider::class);
  $this->app->bind(NodeTransport::class, PollingNodeTransport::class);
 }
 public function boot(): void { Sanctum::ignoreMigrations(); }
}