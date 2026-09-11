<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentProgram;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $headmaster;
    private User $teacher;
    private SchoolClass $schoolClass;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headmaster = User::factory()->create([
            'role' => 'headmaster',
            'status' => 'active',
        ]);

        $this->teacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $department = Department::create([
            'name' => 'Information Technology',
            'code' => 'ICT',
            'description' => null,
            'status' => 'active',
        ]);

        $program = DepartmentProgram::create([
            'department_id' => $department->id,
            'name' => 'Software Development',
            'code' => 'SOD',
            'type' => 'trade',
            'description' => null,
            'status' => 'active',
        ]);

        $this->schoolClass = SchoolClass::create([
            'department_program_id' => $program->id,
            'name' => 'Level 3 Software Development',
            'code' => 'L3SOD',
            'level' => 'Level 3',
            'status' => 'active',
        ]);

        $this->course = Course::create([
            'name' => 'Apply JavaScript',
            'code' => 'SWDJS301',
            'hours' => 100,
            'periods' => 150,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->headmaster);
    }

    public function test_headmaster_can_assign_teacher_to_class_and_course(): void
    {
        $response = $this->postJson('/api/teacher-assignments', [
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $this->schoolClass->id,
            'course_id' => $this->course->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.teacher.id',
                $this->teacher->id
            )
            ->assertJsonPath(
                'data.school_class.id',
                $this->schoolClass->id
            )
            ->assertJsonPath(
                'data.course.id',
                $this->course->id
            );

        $this->assertDatabaseHas('teacher_assignments', [
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $this->schoolClass->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);
    }

    public function test_same_course_cannot_have_two_teachers_in_same_class(): void
    {
        TeacherAssignment::create([
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $this->schoolClass->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        $secondTeacher = User::factory()->create([
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $this->postJson('/api/teacher-assignments', [
            'teacher_id' => $secondTeacher->id,
            'school_class_id' => $this->schoolClass->id,
            'course_id' => $this->course->id,
        ])->assertUnprocessable();
    }

    public function test_non_teacher_cannot_receive_teaching_assignment(): void
    {
        $accountant = User::factory()->create([
            'role' => 'accountant',
            'status' => 'active',
        ]);

        $this->postJson('/api/teacher-assignments', [
            'teacher_id' => $accountant->id,
            'school_class_id' => $this->schoolClass->id,
            'course_id' => $this->course->id,
        ])->assertUnprocessable();
    }

    public function test_teacher_can_become_class_representative(): void
    {
        $this->putJson(
            "/api/school-classes/{$this->schoolClass->id}/representative",
            [
                'teacher_id' => $this->teacher->id,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.representative_teacher.id',
                $this->teacher->id
            );

        $this->assertDatabaseHas('school_classes', [
            'id' => $this->schoolClass->id,
            'representative_teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_teacher_cannot_represent_two_classes(): void
    {
        $this->schoolClass->update([
            'representative_teacher_id' => $this->teacher->id,
        ]);

        $secondClass = SchoolClass::create([
            'department_program_id' =>
                $this->schoolClass->department_program_id,
            'name' => 'Level 4 Software Development',
            'code' => 'L4SOD',
            'level' => 'Level 4',
            'status' => 'active',
        ]);

        $this->putJson(
            "/api/school-classes/{$secondClass->id}/representative",
            [
                'teacher_id' => $this->teacher->id,
            ]
        )->assertUnprocessable();
    }

    public function test_teacher_cannot_manage_assignments(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/teacher-assignments', [
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $this->schoolClass->id,
            'course_id' => $this->course->id,
        ])->assertForbidden();
    }
}
