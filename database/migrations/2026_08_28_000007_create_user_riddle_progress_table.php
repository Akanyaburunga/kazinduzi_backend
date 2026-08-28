<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_riddle_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('riddle_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('hints_revealed')->default(0);
            $table->timestamp('last_hinted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'riddle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_riddle_progress');
    }
};
