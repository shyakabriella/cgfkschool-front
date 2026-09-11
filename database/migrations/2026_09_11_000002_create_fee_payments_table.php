<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            $table->foreignId('school_class_id')
                ->constrained('school_classes')
                ->restrictOnDelete();

            $table->foreignId('fee_item_id')
                ->constrained('fee_items')
                ->restrictOnDelete();

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('receipt_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');

            $table->enum('payment_method', [
                'cash',
                'bank',
                'mobile_money',
            ])->default('cash');

            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', [
                'completed',
                'voided',
            ])->default('completed');

            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique('receipt_number');
            $table->index([
                'student_id',
                'fee_item_id',
                'status',
            ]);
            $table->index([
                'school_class_id',
                'payment_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
