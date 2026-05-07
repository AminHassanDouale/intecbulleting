<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('period');                               // PeriodEnum
            $table->string('status')->default('draft');            // BulletinStatusEnum
            $table->decimal('total_score', 6, 2)->nullable();
            $table->decimal('moyenne', 5, 2)->nullable();
            $table->decimal('class_moyenne', 5, 2)->nullable();
            $table->string('appreciation')->nullable();
            $table->text('teacher_comment')->nullable();
            $table->text('direction_comment')->nullable();
            // ── New columns ───────────────────────────────────────────────
            $table->decimal('total_manuel', 6, 2)->nullable();     // manual override for total score
            $table->decimal('moyenne_10', 4, 2)->nullable();       // moyenne scaled to /10
            $table->decimal('moyenne_classe', 4, 2)->nullable();   // class average (replaces legacy class_moyenne)
            $table->string('discipline_status', 20)->nullable();   // e.g. 'bien', 'passable', 'insuffisant'
            // ─────────────────────────────────────────────────────────────
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pedagogie_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('direction_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('pedagogie_approved_at')->nullable();
            $table->timestamp('finance_approved_at')->nullable();
            $table->timestamp('direction_approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'academic_year_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
    }
};
