<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riddle_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('riddle_categories')->nullOnDelete();
            $table->foreignId('riddle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question', 1000);
            $table->string('answer', 255);
            $table->string('difficulty')->default('easy');
            $table->string('riddle_type')->default('riddle');
            $table->string('hint', 500)->nullable();
            $table->string('hint2', 500)->nullable();
            $table->string('source', 255);
            $table->string('status')->default('pending')->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riddle_submissions');
    }
};
