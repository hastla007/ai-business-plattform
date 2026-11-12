<?php
use App\Http\Middleware\ResolveOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php')
 ->withMiddleware(function (Middleware $middleware): void { $middleware->alias(['organization' => ResolveOrganization::class, 'organization.role' => \App\Http\Middleware\RequireOrganizationRole::class]); })
 ->withExceptions(function (Exceptions $exceptions): void {})
 ->create();