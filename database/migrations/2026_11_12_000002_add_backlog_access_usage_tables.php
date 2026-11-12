<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('assistants',fn(Blueprint $t)=>$t->string('access_mode',16)->default('organization'));
  Schema::create('assistant_access_rules',fn(Blueprint $t)=>[
   $t->id(),$t->foreignId('organization_id')->constrained()->cascadeOnDelete(),
   $t->foreignId('assistant_id')->constrained()->cascadeOnDelete(),$t->string('subject_type',16),
   $t->unsignedBigInteger('subject_id'),$t->foreignId('created_by')->constrained('users')->cascadeOnDelete(),
   $t->timestamps(),$t->unique(['assistant_id','subject_type','subject_id'])
  ]);
  Schema::create('organization_invitations',fn(Blueprint $t)=>[
   $t->id(),$t->foreignId('organization_id')->constrained()->cascadeOnDelete(),$t->string('email'),
   $t->string('role',24)->default('member'),$t->char('token_hash',64)->unique(),
   $t->foreignId('invited_by')->constrained('users')->cascadeOnDelete(),$t->timestamp('expires_at'),
   $t->timestamp('accepted_at')->nullable(),$t->timestamps(),$t->index(['organization_id','email'])
  ]);
  Schema::create('usage_events',fn(Blueprint $t)=>[
   $t->id(),$t->foreignId('organization_id')->constrained()->cascadeOnDelete(),
   $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(),
   $t->foreignId('model_profile_id')->nullable()->constrained()->nullOnDelete(),
   $t->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete(),
   $t->uuid('request_id')->unique(),$t->unsignedInteger('input_tokens')->default(0),
   $t->unsignedInteger('output_tokens')->default(0),$t->unsignedBigInteger('cost_micros')->default(0),
   $t->string('status',16)->default('completed'),$t->timestamp('created_at')->useCurrent(),
   $t->index(['organization_id','created_at'])
  ]);
 }
 public function down():void {
  Schema::dropIfExists('usage_events');Schema::dropIfExists('organization_invitations');
  Schema::dropIfExists('assistant_access_rules');Schema::table('assistants',fn(Blueprint $t)=>$t->dropColumn('access_mode'));
 }
};
