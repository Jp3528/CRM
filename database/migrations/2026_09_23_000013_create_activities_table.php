<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subjectable');
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
