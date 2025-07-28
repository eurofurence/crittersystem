<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\Department;
use Engelsystem\Models\User\User;
use Psr\Log\LoggerInterface;

class DepartmentController extends BaseController
{
    public function __construct(
        protected LoggerInterface $log,
        protected Response $response,
        protected Request $request
    ) {
    }

    public function index(): Response
    {
        $user = auth()->user();
        $query = Department::query();

        if (!$user->groups->contains('name', 'Staff - Internal')) {
            $query->where('staff_only', false);
        }

        $departments = $query->orderBy('name')->get();

        return $this->response->withView(
            'pages/departments/list.twig',
            [
                'departments' => $departments,
                'user_departments' => $user->departments,
                'can_create' => $this->canCreateDepartment($user),
            ]
        );
    }

    public function show(Request $request): Response
    {
        $departmentUUID = $request->getAttribute('uuid');
        $department = Department::where('uuid', $departmentUUID)->firstOrFail();
        $user = auth()->user();

        if ($department->staff_only && !$user->canManageDepartment($department)) {
            dd('not allowed - DepartmentController.php:52');
            //            throw new HttpForbidden();
//            return $this->response->redirectTo('/departments');
        }

        $data = [
            'department' => $department,
            'can_manage' => $user->canManageDepartment($department),
            'pending_users' => $department->pendingUsers,
            'staff_users' => $department->staffUsers,
            'other_users' => $department->otherUsers,
            'locations' => $department->locations,
            'shifts' => $department->shifts,
            'angel_types' => $department->angelTypes,
        ];

        return $this->response->withView('pages/departments/show.twig', $data);
    }

    public function create(): Response
    {
        if (!$this->canCreateDepartment(auth()->user())) {
//            throw new HttpForbidden();
            dd('not allowed - DepartmentController.php:74');
        }

        return $this->response->withView('pages/departments/create.twig');
    }

    public function store(): Response
    {
        if (!$this->canCreateDepartment(auth()->user())) {
//            throw new HttpForbidden();
            dd('not allowed - DepartmentController.php:84');
        }

        $data = $this->validate($this->request, [
            'name' => 'required|max:255',
            'description' => 'optional|max:255',
            'staff_only' => 'optional|checked',
        ]);

//        $department = new Department($data);
        $department = new Department();
        $department->name = $data['name'];
        $department->description = $data['description'];
        $department->staff_only = (bool) $data['staff_only'];
        $department->save();

        $this->log->info('Department {name} created by {user}', [
            'name' => $department->name,
            'user' => auth()->user()->name,
        ]);

        // TODO: Add the other controllers - to add the users responsible and so one

        return $this->response->redirectTo('/departments/' . $department->uuid);
    }

    public function edit(Request $request): Response
    {
        $departmentUUID = $request->getAttribute('uuid');
        $department = Department::where('uuid', $departmentUUID)->firstOrFail();

        if (!auth()->user()->canManageDepartment($department)) {
//            throw new HttpForbidden();
            dd('not allowed - DepartmentController.php:110');
        }

        return $this->response->withView('pages/departments/edit.twig', [
            'department' => $department,
        ]);
    }

    public function update(Request $request): Response
    {
        $departmentUUID = $request->getAttribute('uuid');
        $department = Department::where('uuid', $departmentUUID)->firstOrFail();

        if (!auth()->user()->canManageDepartment($department)) {
//            throw new HttpForbidden();
            dd('not allowed - DepartmentController.php:124');
        }

        $data = $this->validate($this->request, [
            'name' => 'required|max:255',
            'description' => 'optional|max:255',
            'staff_only' => 'optional|checked',
        ]);

        $department->update($data);

        $this->log->info('Department {name} updated by {user}', [
            'name' => $department->name,
            'user' => auth()->user()->name,
        ]);

        return $this->response->redirectTo('/departments/' . $department->uuid);
    }

    public function destroy(Request $request): Response
    {
        $departmentUUID = $request->getAttribute('uuid');
        $department = Department::where('uuid', $departmentUUID)->firstOrFail();

        if (!auth()->user()->canManageDepartment($department)) {
//            throw new HttpForbidden();
            dd('not allowed - DepartmentController.php:149');
        }

        $name = $department->name;
        $department->delete();

        $this->log->info('Department {name} deleted by {user}', [
            'name' => $name,
            'user' => auth()->user()->name,
        ]);

        return $this->response->redirectTo('/departments');
    }

    private function canCreateDepartment(User $user): bool
    {
        return $user->groups->contains('name', 'Shift Coordinator')
            || $user->privileges()->where('name', 'admin')->exists();
    }
}
