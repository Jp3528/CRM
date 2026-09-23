<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('pipeline_stages')->restrictOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['opportunity_id', 'changed_at']);
            $table->index('to_stage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_stage_history');
    }
};
