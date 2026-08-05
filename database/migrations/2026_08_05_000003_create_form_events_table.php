<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lightweight analytics beacons from the public fill page.
        // No PII: the session hash is a salted hash rotated per browser session.
        Schema::create('form_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64);
            $table->string('type', 10); // view|start|step|submit
            $table->unsignedSmallInteger('step')->nullable();
            $table->timestamp('created_at');

            // Funnel aggregation: count by type over a date range.
            $table->index(['form_id', 'type', 'created_at']);
            // Dedupe: one view/start per session.
            $table->index(['form_id', 'session_hash', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_events');
    }
};
