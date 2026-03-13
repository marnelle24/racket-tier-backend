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
            $table->string('primary_sport', 32)->nullable()->after('pronoun');
            $table->string('nickname', 64)->nullable()->after('primary_sport');
            $table->string('avatar_seed', 64)->nullable()->after('nickname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['primary_sport', 'nickname', 'avatar_seed']);
        });
    }
};
