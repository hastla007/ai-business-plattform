<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('model_profiles',fn(Blueprint $table)=>$table->unsignedSmallInteger('embedding_dimensions')->default(1536));
  Schema::table('documents',fn(Blueprint $table)=>$table->string('embedding_model',120)->nullable()->index());
  DB::statement("UPDATE documents d SET embedding_model = m.embedding_model FROM model_profiles m WHERE m.organization_id = d.organization_id AND m.is_active = true AND d.embedding_model IS NULL");
 }
 public function down():void {
  Schema::table('documents',fn(Blueprint $table)=>$table->dropColumn('embedding_model'));
  Schema::table('model_profiles',fn(Blueprint $table)=>$table->dropColumn('embedding_dimensions'));
 }
};
