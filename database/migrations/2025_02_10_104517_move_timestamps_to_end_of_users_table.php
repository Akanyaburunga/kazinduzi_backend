<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        DB::statement('ALTER TABLE users MODIFY created_at TIMESTAMP NULL DEFAULT NULL AFTER verification_expires_at');
        DB::statement('ALTER TABLE users MODIFY updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at');
    }

    public function down()
    {
        // This assumes the original position was after the email column; adjust if needed
        DB::statement('ALTER TABLE users MODIFY created_at TIMESTAMP NULL DEFAULT NULL AFTER remember_token');
        DB::statement('ALTER TABLE users MODIFY updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at');
    }
};
