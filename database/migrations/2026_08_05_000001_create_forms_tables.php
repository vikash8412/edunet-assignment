<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // ULID used in the public fill URL: unguessable and index-friendly.
            $table->ulid('public_id')->unique();
            $table->string('title', 200);
            $table->json('schema');
            $table->string('status', 20)->default('draft'); // draft|published|archived
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Owner dashboard: list my forms filtered by status.
            $table->index(['user_id', 'status']);
        });

        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Where this snapshot came from: builder|ai|import|rollback
            $table->string('source', 20)->default('builder');
            $table->string('label', 60)->nullable();
            $table->timestamp('created_at');

            $table->unique(['form_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('forms');
    }
};
