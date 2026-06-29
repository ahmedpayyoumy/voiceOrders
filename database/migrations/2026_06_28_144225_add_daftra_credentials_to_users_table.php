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
        Schema::table('users', function (Blueprint $table) {
            $table->string('daftra_domain')->nullable()->after('quota_reset_at');
            $table->text('daftra_api_key')->nullable()->after('daftra_domain');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daftra_domain', 'daftra_api_key']);
        });
    }
};
