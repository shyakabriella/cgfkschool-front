<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('school_class_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('school_classes')
                ->nullOnDelete();

            $table->foreignId('course_learning_unit_id')
                ->nullable()
                ->after('school_class_id')
                ->constrained('course_learning_units')
                ->nullOnDelete();

            $table->foreignId(
                'course_indicative_content_id'
            )
                ->nullable()
                ->after('course_learning_unit_id')
                ->constrained(
                    'course_indicative_contents'
                )
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId(
                'course_indicative_content_id'
            );

            $table->dropConstrainedForeignId(
                'course_learning_unit_id'
            );

            $table->dropConstrainedForeignId(
                'school_class_id'
            );
        });
    }
};
