<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedTinyInteger('probability')->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->timestamps();

            $table->unique(['pipeline_id', 'name']);
            $table->unique(['pipeline_id', 'position']);
            $table->index(['pipeline_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
