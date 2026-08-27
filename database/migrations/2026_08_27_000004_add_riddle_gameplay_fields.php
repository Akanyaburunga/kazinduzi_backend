<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add difficulty, source attribution and a second (progressive) hint
     * to the riddles table. Used by the mobile game and the admin panel.
     */
    public function up(): void
    {
        Schema::table('riddles', function (Blueprint $table) {
            // nullable = difficulty not yet set
            $table->string('difficulty', 10)->nullable()->default('easy')->after('question');
            $table->string('source', 255)->nullable()->after('hint');
            $table->text('hint2')->nullable()->after('hint');
        });
    }

    public function down(): void
    {
        Schema::table('riddles', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'source', 'hint2']);
        });
    }
};
