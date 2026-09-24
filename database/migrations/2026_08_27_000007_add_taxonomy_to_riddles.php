<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('riddle_tag', function (Blueprint $table) {
            $table->foreignId('riddle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['riddle_id', 'tag_id']);
        });

        Schema::table('riddles', function (Blueprint $table) {
            $table->string('riddle_type', 32)->nullable()->default('riddle')->after('source');
            $table->unsignedInteger('popularity_score')->default(0)->after('riddle_type');
        });
    }

    public function down(): void
    {
        Schema::table('riddles', function (Blueprint $table) {
            $table->dropColumn(['riddle_type', 'popularity_score']);
        });

        Schema::dropIfExists('riddle_tag');
        Schema::dropIfExists('tags');
    }
};
