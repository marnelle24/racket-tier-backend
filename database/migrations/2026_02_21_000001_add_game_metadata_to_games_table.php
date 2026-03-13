<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('start_time')->nullable()->after('status');
            $table->timestamp('end_time')->nullable()->after('start_time');
            $table->string('score')->nullable()->after('end_time');
            $table->string('match_type')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'score', 'match_type']);
        });
    }
};
