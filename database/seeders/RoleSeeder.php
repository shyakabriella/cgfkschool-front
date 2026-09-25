<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Seed the school system roles.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Headmaster',
                'slug' => 'headmaster',
                'description' => 'Views and supervises all school activities.',
            ],
            [
                'name' => 'Director of Studies',
                'slug' => 'director_of_studies',
                'description' => 'Manages academic activities, classes, subjects, marks, and reports.',
            ],
            [
                'name' => 'Discipline Master',
                'slug' => 'discipline_master',
                'description' => 'Monitors students, discipline, and attendance.',
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Registers students and manages school fees and financial reports.',
            ],
            [
                'name' => 'Teacher',
                'slug' => 'teacher',
                'description' => 'Manages assigned classes, attendance, and student marks.',
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Accesses personal academic information, assessments, results, and reports.',
            ],
            [
                'name' => 'Matron',
                'slug' => 'matron',
                'description' => 'Manages the welfare and supervision of assigned female students.',
            ],
            [
                'name' => 'Patron',
                'slug' => 'patron',
                'description' => 'Manages the welfare and supervision of assigned male students.',
            ],
        ];

        DB::table('roles')
            ->where('slug', 'director_of_of_studies')
            ->delete();

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
