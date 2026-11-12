<?php
use App\Http\Controllers\{AgentController,AuthController,ChatController,DocumentController};
use Illuminate\Support\Facades\Route;
Route::post('/v1/register',[AuthController::class,'register'])->middleware('throttle:5,1');
Route::post('/v1/login',[AuthController::class,'login'])->middleware('throttle:10,1');
Route::middleware(['auth:sanctum','organization'])->prefix('v1')->group(function () {
 Route::middleware('organization.role:owner,admin')->group(function(){
  Route::get('/groups',[\App\Http\Controllers\GroupController::class,'index']);
  Route::post('/groups',[\App\Http\Controllers\GroupController::class,'store']);
  Route::put('/groups/{group}/members/{user}',[\App\Http\Controllers\GroupController::class,'addMember']);
  Route::delete('/groups/{group}/members/{user}',[\App\Http\Controllers\GroupController::class,'removeMember']);
 });
 Route::post('/logout',[AuthController::class,'logout']);
 Route::post('/chat',ChatController::class);
 Route::get('/documents',[DocumentController::class,'index']);
 Route::post('/documents',[DocumentController::class,'store']);
 Route::put('/documents/{document}',[DocumentController::class,'update']);
 Route::delete('/documents/{document}',[DocumentController::class,'destroy']);
 Route::get('/agents',[AgentController::class,'index']);
 Route::post('/agents/{assistant}/runs',[AgentController::class,'store']);
 Route::get('/agent-runs/{run}',[AgentController::class,'show']);
});