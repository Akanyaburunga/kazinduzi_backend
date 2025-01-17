<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('reputation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('change'); // The change in reputation (+/- points)
            $table->string('reason'); // Reason for the change
            $table->foreignId('related_id')->nullable(); // ID of related resource (e.g., meaning, vote)
            $table->string('related_type')->nullable(); // Related model (e.g., "Meaning")
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reputation_logs');
    }
};
