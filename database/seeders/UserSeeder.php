<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Create the initial system administrator.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@cgfk.com',
            ],
            [
                'name' => 'System Administrator',
                'phone' => null,
                'role' => 'headmaster',
                'password' => Hash::make('Rwanda@1'),
                'status' => 'active',
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
