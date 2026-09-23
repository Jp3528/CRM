<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->unsignedTinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();
            $table->string('status', 50)->default('open')->index();
            $table->text('loss_reason')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained('pipelines')->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained('pipeline_stages')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pipeline_id', 'pipeline_stage_id']);
            $table->index('owner_id');
            $table->index('company_id');
            $table->index('contact_id');
            $table->index(['status', 'expected_close_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
