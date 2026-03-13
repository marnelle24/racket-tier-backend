<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('join_token', 64)->unique()->nullable()->after('name');
        });

        // Backfill existing facilities with a generated token
        foreach (DB::table('facilities')->get() as $facility) {
            DB::table('facilities')
                ->where('id', $facility->id)
                ->update(['join_token' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('join_token');
        });
    }
};
