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
        Schema::create('guest_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 10);
            $table->string('puzzle_type', 20);
            $table->unsignedBigInteger('puzzle_id');
            $table->timestamps();

            $table->unique(['user_id', 'mode', 'puzzle_type', 'puzzle_id']);
            $table->index(['user_id', 'mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_plays');
    }
};