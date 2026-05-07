<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // FRANÇAIS, MATHÉMATIQUES
            $table->string('code');
            $table->foreignId('niveau_id')->constrained()->cascadeOnDelete();
            $table->string('classroom_code')->nullable();   // CP, CE1 etc. (legacy, level grouping)
            $table->string('section_code', 10)->nullable()->index(); // CPA, CE1B etc. NULL = shared across all sections
            $table->integer('max_score')->default(20);
            $table->string('scale_type');                   // ScaleTypeEnum
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
