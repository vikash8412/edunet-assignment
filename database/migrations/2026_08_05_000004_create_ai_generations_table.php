<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null for "create from scratch"; set when editing an existing form.
            $table->foreignId('form_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('mode', 10); // create|edit
            $table->text('prompt');
            $table->string('status', 10)->default('queued'); // queued|running|done|failed
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->json('result_schema')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            // Status polling from the builder UI.
            $table->index(['user_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
