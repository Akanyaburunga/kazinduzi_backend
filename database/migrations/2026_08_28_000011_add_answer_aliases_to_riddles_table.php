<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow a riddle to carry many accepted answer variants (each may itself
     * use `/`-separated alternatives) for lenient matching.
     */
    public function up(): void
    {
        Schema::table('riddles', function (Blueprint $table) {
            $table->text('answer_aliases')->nullable()->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('riddles', function (Blueprint $table) {
            $table->dropColumn('answer_aliases');
        });
    }
};
