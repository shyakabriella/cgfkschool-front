<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentProgram;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $headmaster = User::factory()->create([
            'role' => 'headmaster',
            'status' => 'active',
        ]);

        Sanctum::actingAs($headmaster);
    }

    public function test_headmaster_can_create_complete_academic_structure(): void
    {
        $departmentResponse = $this->postJson('/api/departments', [
            'name' => 'Technical Department',
            'code' => 'TECH',
            'description' => 'Technical education department.',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'TECH');

        $departmentId = $departmentResponse->json('data.id');

        $programResponse = $this->postJson(
            '/api/department-programs',
            [
                'department_id' => $departmentId,
                'name' => 'Software Development',
                'code' => 'SWD',
                'type' => 'trade',
                'status' => 'active',
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.type', 'trade');

        $programId = $programResponse->json('data.id');

        $this->postJson('/api/school-classes', [
            'department_program_id' => $programId,
            'name' => 'Level 3 Software Development',
            'code' => 'L3-SWD',
            'level' => 'Level 3',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'L3-SWD');

        $this->assertDatabaseHas('departments', [
            'code' => 'TECH',
        ]);

        $this->assertDatabaseHas('department_programs', [
            'code' => 'SWD',
        ]);

        $this->assertDatabaseHas('school_classes', [
            'code' => 'L3-SWD',
        ]);
    }

    public function test_department_name_and_code_must_be_unique(): void
    {
        Department::create([
            'name' => 'Technical Department',
            'code' => 'TECH',
            'status' => 'active',
        ]);

        $this->postJson('/api/departments', [
            'name' => 'Technical Department',
            'code' => 'TECH',
            'status' => 'active',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'code',
            ]);
    }

    public function test_program_cannot_be_created_under_inactive_department(): void
    {
        $department = Department::create([
            'name' => 'Inactive Department',
            'code' => 'INACTIVE',
            'status' => 'inactive',
        ]);

        $this->postJson('/api/department-programs', [
            'department_id' => $department->id,
            'name' => 'Test Trade',
            'code' => 'TEST',
            'type' => 'trade',
        ])->assertUnprocessable();
    }

    public function test_department_with_programs_cannot_be_archived(): void
    {
        $department = Department::create([
            'name' => 'Technical Department',
            'code' => 'TECH',
            'status' => 'active',
        ]);

        DepartmentProgram::create([
            'department_id' => $department->id,
            'name' => 'Software Development',
            'code' => 'SWD',
            'type' => 'trade',
            'status' => 'active',
        ]);

        $this->deleteJson(
            "/api/departments/{$department->id}"
        )->assertUnprocessable();
    }

    public function test_teacher_cannot_manage_academic_structure(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        Sanctum::actingAs($teacher);

        $this->postJson('/api/departments', [
            'name' => 'Unauthorized Department',
            'code' => 'NO',
        ])->assertForbidden();
    }

    public function test_class_is_soft_deleted(): void
    {
        $department = Department::create([
            'name' => 'Technical Department',
            'code' => 'TECH',
            'status' => 'active',
        ]);

        $program = DepartmentProgram::create([
            'department_id' => $department->id,
            'name' => 'Software Development',
            'code' => 'SWD',
            'type' => 'trade',
            'status' => 'active',
        ]);

        $schoolClass = SchoolClass::create([
            'department_program_id' => $program->id,
            'name' => 'Level 3 Software Development',
            'code' => 'L3-SWD',
            'level' => 'Level 3',
            'status' => 'active',
        ]);

        $this->deleteJson(
            "/api/school-classes/{$schoolClass->id}"
        )->assertOk();

        $this->assertSoftDeleted('school_classes', [
            'id' => $schoolClass->id,
        ]);
    }
}
