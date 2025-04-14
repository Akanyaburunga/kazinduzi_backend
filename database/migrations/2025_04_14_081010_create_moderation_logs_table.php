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
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_by')->constrained('users');
            $table->foreignId('target_user')->nullable()->constrained('users');
            $table->foreignId('word_id')->nullable()->constrained('words');
            $table->foreignId('meaning_id')->nullable()->constrained('meanings');
            $table->string('action'); // e.g. 'ban_user', 'suspend_word'
            $table->text('reason')->nullable();
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
