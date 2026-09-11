<?php

namespace App\Http\Controllers\API;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends BaseController
{
    /**
     * Display all roles.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureHeadmaster($request);

        $roles = Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            $roles,
            'Roles retrieved successfully.'
        );
    }

    /**
     * Create a new role.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureHeadmaster($request);

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:roles,name',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The provided role information is invalid.',
                $validator->errors(),
                422
            );
        }

        $slug = Str::slug($request->name, '_');

        if (Role::where('slug', $slug)->exists()) {
            return $this->sendError(
                'A role with a similar name already exists.',
                [
                    'name' => [
                        'Please use a different role name.',
                    ],
                ],
                422
            );
        }

        $role = Role::create([
            'name' => trim($request->name),
            'slug' => $slug,
            'description' => $request->description
                ? trim($request->description)
                : null,
        ]);

        return $this->sendCreated(
            $role,
            'Role created successfully.'
        );
    }

    /**
     * Display one role.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        $this->ensureHeadmaster($request);

        $role->loadCount('users');

        return $this->sendResponse(
            $role,
            'Role retrieved successfully.'
        );
    }

    /**
     * Update a role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->ensureHeadmaster($request);

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The provided role information is invalid.',
                $validator->errors(),
                422
            );
        }

        $newSlug = Str::slug($request->name, '_');

        $slugExists = Role::query()
            ->where('slug', $newSlug)
            ->where('id', '!=', $role->id)
            ->exists();

        if ($slugExists) {
            return $this->sendError(
                'A role with a similar name already exists.',
                [
                    'name' => [
                        'Please use a different role name.',
                    ],
                ],
                422
            );
        }

        $oldSlug = $role->slug;

        $role->update([
            'name' => trim($request->name),
            'slug' => $newSlug,
            'description' => $request->description
                ? trim($request->description)
                : null,
        ]);

        /*
         * Update users because the current users table stores the role slug.
         */
        if ($oldSlug !== $newSlug) {
            User::where('role', $oldSlug)->update([
                'role' => $newSlug,
            ]);
        }

        return $this->sendResponse(
            $role->fresh()->loadCount('users'),
            'Role updated successfully.'
        );
    }

    /**
     * Delete an unused role.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->ensureHeadmaster($request);

        $systemRoles = [
            'headmaster',
            'director_of_studies',
            'discipline_master',
            'accountant',
            'teacher',
            'matron',
            'patron',
        ];

        if (in_array($role->slug, $systemRoles, true)) {
            return $this->sendError(
                'A default system role cannot be deleted.',
                null,
                422
            );
        }

        $roleIsUsed = User::where('role', $role->slug)->exists();

        if ($roleIsUsed) {
            return $this->sendError(
                'This role cannot be deleted because it is assigned to users.',
                null,
                422
            );
        }

        $role->delete();

        return $this->sendResponse(
            null,
            'Role deleted successfully.'
        );
    }

    /**
     * Ensure that only the Headmaster manages roles.
     */
    private function ensureHeadmaster(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole('headmaster'),
            403,
            'Only the Headmaster can manage roles.'
        );
    }
}
