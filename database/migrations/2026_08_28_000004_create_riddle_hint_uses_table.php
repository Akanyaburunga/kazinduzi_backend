<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riddle_hint_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('riddle_id')->constrained()->onDelete('cascade');
            $table->integer('count')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'riddle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riddle_hint_uses');
    }
};
