<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_participants', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('result_confirmed_at');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('stats_applied_at')->nullable()->after('winner_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_participants', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('stats_applied_at');
        });
    }
};
