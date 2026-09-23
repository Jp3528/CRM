<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('source', 100)->nullable()->index();
            $table->string('status', 50)->default('new')->index();
            $table->unsignedSmallInteger('score')->default(0);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('converted_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index(['status', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
