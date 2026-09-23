<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('other');
            $table->string('status', 30)->default('draft');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_at')->nullable();
            $table->date('end_at')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->decimal('expected_revenue', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('owner_id');
            $table->index('status');
            $table->index('type');
            $table->index('start_at');
            $table->index('end_at');
        });

        Schema::create('campaign_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            // Clave segura (contact|lead). Nunca una clase PHP arbitraria.
            $table->string('member_type', 20);
            $table->unsignedBigInteger('member_id');
            $table->string('status', 30)->default('pending');
            $table->string('source', 100)->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'member_type', 'member_id'], 'campaign_members_unique');
            $table->index(['member_type', 'member_id']);
            $table->index('status');
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 30)->default('email');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 30)->default('draft');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('owner_id');
            $table->index('channel');
            $table->index('status');
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('campaign_member_id')->nullable()->constrained('campaign_members')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('channel', 30)->default('email');
            $table->string('direction', 20)->default('outbound');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 30)->default('draft');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('campaign_id');
            $table->index('campaign_member_id');
            $table->index('contact_id');
            $table->index('lead_id');
            $table->index('channel');
            $table->index('status');
            $table->index('sent_at');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('campaign_members');
        Schema::dropIfExists('campaigns');
    }
};
