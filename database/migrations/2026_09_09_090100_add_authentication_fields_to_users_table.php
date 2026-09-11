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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)
                ->nullable()
                ->unique()
                ->after('email');

            $table->string('role', 100)
                ->default('teacher')
                ->index()
                ->after('phone');

            $table->string('status', 20)
                ->default('active')
                ->index()
                ->after('password');

            $table->boolean('must_change_password')
                ->default(true)
                ->after('status');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('must_change_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->dropIndex('users_role_index');
            $table->dropIndex('users_status_index');

            $table->dropColumn([
                'phone',
                'role',
                'status',
                'must_change_password',
                'last_login_at',
            ]);
        });
    }
};
