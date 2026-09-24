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
        Schema::create('riddles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('riddle_categories')->onDelete('set null');
            $table->text('question');
            $table->string('answer', 255);
            $table->text('hint')->nullable();
            $table->boolean('is_suspended')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riddles');
    }
};
