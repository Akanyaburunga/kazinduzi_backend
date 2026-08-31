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
        Schema::create('round_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->onDelete('cascade');
            $table->string('puzzle_type', 16);
            $table->unsignedBigInteger('puzzle_id');
            $table->unsignedTinyInteger('position');
            $table->string('status', 16)->default('pending');
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['round_id', 'position']);
            $table->index(['puzzle_type', 'puzzle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_items');
    }
};
