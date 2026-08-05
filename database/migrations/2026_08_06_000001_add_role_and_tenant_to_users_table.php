<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No backfill path — this app resets via migrate:fresh --seed, so existing
 * rows never need a data migration for these new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 10)->default('user')->after('id'); // super|tenant|user
            $table->foreignId('tenant_id')->nullable()->after('role')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable()->after('tenant_id');

            $table->index('role');
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['role', 'disabled_at']);
        });
    }
};
