<?php
namespace App\Providers;
use App\Services\ModelProvider;
use App\Services\OpenAiCompatibleProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
class AppServiceProvider extends ServiceProvider {
 public function register(): void { $this->app->bind(ModelProvider::class, OpenAiCompatibleProvider::class); }
 public function boot(): void { Sanctum::ignoreMigrations(); }
}