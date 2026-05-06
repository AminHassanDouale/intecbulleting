<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->unsignedSmallInteger('order')->default(0)->after('cycle');
        });

        // Seed default order based on existing records
        DB::table('niveaux')->orderBy('id')->get()->each(function ($n, $i) {
            DB::table('niveaux')->where('id', $n->id)->update(['order' => $i + 1]);
        });
    }

    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
