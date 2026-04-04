<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletin_teacher_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulletin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft | submitted
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['bulletin_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_teacher_submissions');
    }
};
