<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('words', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('word'); // Allow null values temporarily
        });

        // Generate unique slugs for existing records
        $words = \App\Models\Word::all();
        foreach ($words as $word) {
            $word->slug = \Illuminate\Support\Str::slug($word->word . '-' . $word->id);
            $word->save();
        }

        Schema::table('words', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change(); // Make it unique and non-null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('words', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
