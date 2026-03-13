<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_winners', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['game_id', 'user_id']);
            $table->index('user_id');
        });

        $gamesWithWinner = DB::table('games')->whereNotNull('winner_id')->get();
        foreach ($gamesWithWinner as $game) {
            DB::table('game_winners')->insert([
                'game_id' => $game->id,
                'user_id' => $game->winner_id,
            ]);
        }

        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['winner_id']);
            $table->dropColumn('winner_id');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('winner_id')->nullable()->after('creator_id')->constrained('users')->nullOnDelete();
        });

        $games = DB::table('game_winners')
            ->select('game_id')
            ->distinct()
            ->get();

        foreach ($games as $row) {
            $firstWinner = DB::table('game_winners')
                ->where('game_id', $row->game_id)
                ->orderBy('user_id')
                ->first();
            if ($firstWinner) {
                DB::table('games')
                    ->where('id', $row->game_id)
                    ->update(['winner_id' => $firstWinner->user_id]);
            }
        }

        Schema::dropIfExists('game_winners');
    }
};
