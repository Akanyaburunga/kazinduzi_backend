<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_banned')->default(false);
        });

        Schema::table('words', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false);
        });

        Schema::table('meanings', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_banned');
        });

        Schema::table('words', function (Blueprint $table) {
            $table->dropColumn('is_suspended');
        });

        Schema::table('meanings', function (Blueprint $table) {
            $table->dropColumn('is_suspended');
        });
    }
};
