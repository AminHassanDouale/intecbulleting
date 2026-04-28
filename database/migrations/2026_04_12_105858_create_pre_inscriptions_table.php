<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pre_inscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year')->default('2026-2027');
            $table->string('student_firstname');
            $table->string('student_lastname');
            $table->date('student_birth_date')->nullable();
            $table->enum('student_gender', ['M','F'])->nullable();
            $table->string('niveau_souhaite'); // PS,MS,GS,CP,CE1,CE2,CM1,CM2
            $table->string('parent_name');
            $table->string('parent_phone');
            $table->string('parent_email')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending','contacted','accepted','rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_inscriptions');
    }
};
