<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->nullable()->unique();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('father_name', 150)->nullable();
            $table->string('mother_name', 150)->nullable();
            $table->string('parent_contact', 30);

            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_contact', 30)->nullable();
            $table->string('guardian_relationship', 100)->nullable();

            $table->date('date_of_birth');
            $table->foreignId('school_class_id')
                ->constrained('school_classes')
                ->restrictOnDelete();

            $table->string('province', 100);
            $table->string('district', 100);
            $table->string('sector', 100);
            $table->string('cell', 100);
            $table->string('village', 100);

            $table->string('previous_school_name', 200)->nullable();
            $table->json('documents')->nullable();
            $table->string('image')->nullable();

            $table->enum('status', [
                'active',
                'inactive',
                'graduated',
                'transferred',
                'withdrawn',
            ])->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_class_id', 'status']);
            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
