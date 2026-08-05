<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalized tenant_id on each ownership table (rather than resolved via a
 * join through users) so the existing hot-path composite indexes keep their
 * shape — every dashboard listing and AI/import status poll already leads
 * with this column. user_id is kept as "who personally created this record"
 * (an audit/author field, same role form_versions.created_by already plays)
 * and is never used for access control after this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB requires an index whose LEADING column is user_id to
        // back the forms_user_id_foreign constraint — the composite
        // ['user_id','status'] index currently serves that role. A new
        // ['tenant_id','status'] index doesn't help user_id's FK at all, so
        // a plain single-column user_id index is added first to take over,
        // which then frees the old composite to be dropped.
        Schema::table('forms', function (Blueprint $table) {
            $table->index('user_id', 'forms_user_id_fk_index');
            $table->foreignId('tenant_id')->after('user_id')->constrained('users')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });
        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('ai_generations', function (Blueprint $table) {
            $table->index('user_id', 'ai_generations_user_id_fk_index');
            $table->foreignId('tenant_id')->after('user_id')->constrained('users')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'id']);
        });
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'id']);
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->index('user_id', 'imports_user_id_fk_index');
            $table->foreignId('tenant_id')->after('user_id')->constrained('users')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'id']);
        });
        Schema::table('imports', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        // Drop each FK constraint first (frees its backing index), then the
        // now-unused indexes, then restore the original composite + drop the
        // temporary single-column one added in up().
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn('tenant_id');
            $table->index(['user_id', 'status']);
        });
        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex('forms_user_id_fk_index');
        });

        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'id']);
            $table->dropColumn('tenant_id');
            $table->index(['user_id', 'status', 'id']);
        });
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropIndex('ai_generations_user_id_fk_index');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
        Schema::table('imports', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'id']);
            $table->dropColumn('tenant_id');
            $table->index(['user_id', 'status', 'id']);
        });
        Schema::table('imports', function (Blueprint $table) {
            $table->dropIndex('imports_user_id_fk_index');
        });
    }
};
