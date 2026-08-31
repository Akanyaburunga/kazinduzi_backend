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
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('mode', 16);
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedTinyInteger('item_count')->default(10);
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedTinyInteger('current_streak')->default(0);
            $table->unsignedTinyInteger('best_streak')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'mode', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
