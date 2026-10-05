<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maqsam_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('InquiryId')->unique(); // The unique identifier for the inquiry.
            $table->string('Event')->nullable(); // The name of the event (e.g., 'terminated').
            $table->string('FromState')->nullable(); // The state before the transition.
            $table->string('ToState')->nullable(); // The state after the transition.
            $table->integer('Trials')->nullable(); // The number of trials.
            $table->string('UnreachableReason')->nullable(); // The reason for being unreachable (if applicable).
            $table->integer('Length')->nullable(); // The length of the call in seconds (if applicable).
            $table->string('Result')->nullable(); // The final valid entry (if applicable).
            $table->string('Entries')->nullable(); // Comma-separated list of all entries (valid/invalid).
            $table->string('RecordingUrl')->nullable(); // URL to the recorded message (if applicable).

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maqsam_callbacks');
    }
};
