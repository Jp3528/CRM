<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('password')
                ->constrained('teams')->nullOnDelete();
            $table->string('status', 30)->default('active')->after('team_id')->index();
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['status', 'last_login_at', 'deleted_at']);
        });
    }
};
