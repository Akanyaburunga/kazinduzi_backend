<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riddles', function (Blueprint $table) {
            $table->string('suspended_reason')->nullable()->after('is_suspended');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('riddles', function (Blueprint $table) {
            $table->dropColumn('suspended_reason');
            $table->dropSoftDeletes();
        });
    }
};
