<?php

use App\Http\Controllers\API\FinanceLookupController;

use App\Http\Controllers\API\FeePaymentController;

use App\Http\Controllers\API\FeeItemController;

use App\Http\Controllers\API\TeacherLookupController;

use App\Http\Controllers\API\PasswordResetController;

use App\Http\Controllers\API\AttendanceController;

use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\DepartmentProgramController;
use App\Http\Controllers\API\PasswordController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\SchoolClassController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\RwandaLocationController;
use App\Http\Controllers\API\CourseController;
use App\Http\Controllers\API\TeacherAssignmentController;
use App\Http\Controllers\API\ClassRepresentativeController;

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [RegisterController::class, 'login']);

    Route::post('/forgot-password', [
        PasswordResetController::class,
        'forgotPassword',
    ]);

    Route::post('/reset-password', [
        PasswordResetController::class,
        'resetPassword',
    ]);

});

Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
    Route::apiResource(
        'teacher-assignments',
        TeacherAssignmentController::class
    );

    Route::get(
        'class-representatives',
        [ClassRepresentativeController::class, 'index']
    );

    Route::put(
        'school-classes/{schoolClass}/representative',
        [ClassRepresentativeController::class, 'update']
    );
    Route::apiResource(
        'courses',
        CourseController::class
    );
    Route::get('/staff/roles', [
        RegisterController::class,
        'staffRoles',
    ]);

    Route::get('/staff', [
        RegisterController::class,
        'staff',
    ]);

    Route::post('/staff', [
        RegisterController::class,
        'createStaff',
    ]);

    Route::get(
        'rwanda-locations',
        [RwandaLocationController::class, 'index']
    );
    Route::apiResource(
        'students',
        StudentController::class
    )->only([
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ]);
    Route::get('/me', [RegisterController::class, 'me']);
    Route::post('/logout', [RegisterController::class, 'logout']);

    Route::post(
        '/change-password',
        [PasswordController::class, 'changePassword']
    )->middleware('throttle:5,1');

    Route::apiResource('roles', RoleController::class);

    Route::apiResource(
        'departments',
        DepartmentController::class
    );

    Route::apiResource(
        'department-programs',
        
        DepartmentProgramController::class
    );

    Route::apiResource(
        'school-classes',
        SchoolClassController::class
    );


    Route::get(
        '/attendance/classes/{schoolClass}/students',
        [AttendanceController::class, 'classStudents']
    );

    Route::apiResource(
        'attendances',
        AttendanceController::class
    )->only(['index', 'store', 'show']);


    Route::get('/teachers', [
        TeacherLookupController::class,
        'index',
    ]);


    Route::apiResource(
        'fee-items',
        FeeItemController::class
    )->only(['index', 'store', 'update', 'destroy']);


    Route::apiResource(
        'fee-payments',
        FeePaymentController::class
    )->only(['index', 'store', 'update', 'destroy']);


    Route::get('/finance/classes', [
        FinanceLookupController::class,
        'classes',
    ]);

    Route::get('/finance/classes/{schoolClass}/students', [
        FinanceLookupController::class,
        'students',
    ]);

});
