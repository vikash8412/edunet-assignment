<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Set once the parsed schema is committed as a real form.
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('kind', 10); // docx|xlsx
            $table->string('status', 10)->default('queued'); // queued|parsing|ready|committed|failed
            $table->json('parsed_schema')->nullable();
            // Unparseable blocks and heuristic notes, shown on the preview screen.
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
