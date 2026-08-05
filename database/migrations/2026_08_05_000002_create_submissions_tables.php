<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            // Pin each submission to the schema version it was filled against,
            // so old answers always pair with the fields that produced them.
            $table->foreignId('form_version_id')->nullable()->constrained('form_versions')->nullOnDelete();
            $table->json('data');
            // Denormalised copy of the answer values for fast searching.
            $table->text('search_text')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('created_at');

            // Paginated per-form listing, newest first.
            $table->index(['form_id', 'id']);
            $table->index(['form_id', 'created_at']);
            // Per-IP daily caps and spam throttling.
            $table->index(['form_id', 'ip_hash', 'created_at']);
        });

        // FULLTEXT is a MySQL/MariaDB feature; SQLite (local dev/tests)
        // falls back to LIKE queries in the query layer.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->fullText('search_text');
            });
        }

        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 64);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('created_at');

            $table->index(['submission_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('submissions');
    }
};
