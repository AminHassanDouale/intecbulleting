<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletin_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulletin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competence_id')->constrained()->cascadeOnDelete();
            $table->string('period');                           // T1, T2, T3
            $table->decimal('score', 5, 2)->nullable();        // Note numérique
            $table->string('competence_status')->nullable();   // A / EVA / NA
            $table->timestamps();

            $table->unique(['bulletin_id', 'competence_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_grades');
    }
};
