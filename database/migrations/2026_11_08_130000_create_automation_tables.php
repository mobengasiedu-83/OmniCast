<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->string('trigger', 40);
            $table->json('trigger_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('runs_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
            $table->index(['trigger', 'is_active']);
        });

        Schema::create('automation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('action', 32);
            $table->string('label', 80)->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['automation_workflow_id', 'position']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger', 40);
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('current_position')->default(1);
            $table->json('context')->nullable();
            $table->string('detail', 255)->nullable();
            $table->timestamp('resume_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['automation_workflow_id', 'created_at']);
            $table->index(['client_id', 'status']);
            $table->index(['status', 'resume_at']);
        });

        Schema::create('automation_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_step_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('action', 32);
            $table->string('status', 16);
            $table->string('detail', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();

            $table->index(['automation_run_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_run_steps');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_steps');
        Schema::dropIfExists('automation_workflows');
    }
};
