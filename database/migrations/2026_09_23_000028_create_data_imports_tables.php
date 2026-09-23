<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('module', 30);
            $table->string('original_filename', 255);
            $table->string('status', 30)->default('processing');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index('created_by');
            $table->index('module');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('data_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->constrained('data_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('field', 100)->nullable();
            $table->string('message', 500);
            $table->timestamps();
            $table->index(['data_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_errors');
        Schema::dropIfExists('data_imports');
    }
};
