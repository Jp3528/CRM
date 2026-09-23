<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('trade_name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 50)->nullable()->unique();
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('industry', 100)->nullable()->index();
            $table->string('company_size', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->nullable()->index();
            $table->string('postal_code', 20)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('trade_name');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
