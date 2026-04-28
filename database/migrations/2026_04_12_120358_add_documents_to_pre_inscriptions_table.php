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
        Schema::table('pre_inscriptions', function (Blueprint $table) {
            $table->string('student_photo')->nullable()->after('niveau_souhaite');
            $table->string('student_birth_certificate')->nullable()->after('student_photo');
            $table->json('parent_documents')->nullable()->after('student_birth_certificate');
        });
    }

    public function down(): void
    {
        Schema::table('pre_inscriptions', function (Blueprint $table) {
            $table->dropColumn(['student_photo', 'student_birth_certificate', 'parent_documents']);
        });
    }
};
