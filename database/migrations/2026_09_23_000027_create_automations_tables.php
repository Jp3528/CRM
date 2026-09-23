<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('trigger_type', 60);
            $table->jsonb('conditions')->nullable();
            $table->jsonb('actions')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('trigger_type');
            $table->index('owner_id');
            $table->index('last_run_at');
            $table->index(['status', 'trigger_type']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            // Null si la automatización se elimina de forma dura (la UI solo
            // hace soft delete, que conserva el historial intacto).
            $table->foreignId('automation_id')->nullable()->constrained('automations')->nullOnDelete();
            $table->uuid('event_uuid');
            $table->string('trigger_type', 60);
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 30)->default('running');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('context')->nullable();
            $table->jsonb('result')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['automation_id', 'event_uuid']);
            $table->index('automation_id');
            $table->index('event_uuid');
            $table->index(['subject_type', 'subject_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automations');
    }
};
