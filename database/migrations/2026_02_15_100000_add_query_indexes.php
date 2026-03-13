<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for common query patterns. Light optimization only.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->index(['facility_id', 'status']);
        });

        Schema::table('facility_presences', function (Blueprint $table) {
            $table->index(['facility_id', 'last_seen_at']);
        });

        Schema::table('player_stats', function (Blueprint $table) {
            $table->unique(['user_id', 'facility_id']);
            $table->index(['facility_id', 'points']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['facility_id', 'status']);
        });

        Schema::table('facility_presences', function (Blueprint $table) {
            $table->dropIndex(['facility_id', 'last_seen_at']);
        });

        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'facility_id']);
            $table->dropIndex(['facility_id', 'points']);
        });
    }
};
