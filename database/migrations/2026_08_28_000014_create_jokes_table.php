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
        Schema::create('jokes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('riddle_categories')->nullOnDelete();
            $table->string('setup', 1000);
            $table->string('punchline', 500);
            $table->json('distractors')->nullable();
            $table->string('source', 255)->nullable();
            $table->boolean('is_suspended')->default(false);
            $table->string('suspended_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jokes');
    }
};