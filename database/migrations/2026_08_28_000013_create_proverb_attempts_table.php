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
        Schema::create('proverb_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('proverb_id')->constrained()->onDelete('cascade');
            $table->string('submitted_answer', 255);
            $table->boolean('is_correct')->default(false);
            $table->boolean('rewarded')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'proverb_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proverb_attempts');
    }
};
