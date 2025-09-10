<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Department;

use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\BaseModel;
use Engelsystem\Models\Department\Department;
use Engelsystem\Models\Department\DepartmentApplicationLog;
use Psr\Log\LoggerInterface;

class DepartmentApplicationController extends BaseModel
{
    public function __construct(
        protected LoggerInterface $log,
        protected Response $response,
        protected Request $request
    ) {
        parent::__construct();
    }

    public function apply(string $uuid): Response
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();
        $user = auth()->user();

//        if ($department->staff_only && !$user->groups->contains('name', 'Staff - Internal')) {
        if (
            $department->staff_only && !auth()->canAny(
                [
                'user.type.internal_staff',
                'user.type.staff',
                'user.type.admin',
                ]
            )
        ) {
//            throw new HttpForbidden();
            dd('not allowed - DepartmentApplicationController.php:33');
        }

        if ($user->departments()->where('department_id', $department->id)->exists()) {
//            throw new ValidationException('Already applied or member of this department');
            dd('not allowed - DepartmentApplicationController.php:38');
        }

        $user->departments()->attach($department, ['status' => 'pending']);

        $log = new DepartmentApplicationLog([
            'department_id' => $department->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $log->save();

        $this->log->info('User {user} applied to department {department}', [
            'user' => $user->name,
            'department' => $department->name,
        ]);

        return $this->response->redirectTo('/departments/' . $department->uuid);
    }

    public function approve(string $uuid, int $userId): Response
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();
        $user = auth()->user();

        if (!$user->canManageDepartment($department)) {
//            throw new HttpForbidden();
            dd('not allowed - DepartmentApplicationController.php:65');
        }

        $department->users()
            ->wherePivot('user_id', $userId)
            ->wherePivot('status', 'pending')
            ->updateExistingPivot($userId, ['status' => 'approved']);

        $log = new DepartmentApplicationLog([
            'department_id' => $department->id,
            'user_id' => $userId,
            'processed_by' => $user->id,
            'status' => 'approved',
        ]);
        $log->save();

        $this->log->info('Application for department {department} approved for user {target} by {user}', [
            'department' => $department->name,
            'target' => $userId,
            'user' => $user->name,
        ]);

        return $this->response->redirectTo('/departments/' . $department->uuid);
    }

    public function deny(string $uuid, int $userId): Response
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();
        $user = auth()->user();

        if (!$user->canManageDepartment($department)) {
//            throw new HttpForbidden();
            dd('not allowed - DepartmentApplicationController.php:97');
        }

        $department->users()
            ->wherePivot('user_id', $userId)
            ->wherePivot('status', 'pending')
            ->updateExistingPivot($userId, ['status' => 'denied']);

        $log = new DepartmentApplicationLog([
            'department_id' => $department->id,
            'user_id' => $userId,
            'processed_by' => $user->id,
            'status' => 'denied',
        ]);
        $log->save();

        $this->log->info('Application for department {department} denied for user {target} by {user}', [
            'department' => $department->name,
            'target' => $userId,
            'user' => $user->name,
        ]);

        return $this->response->redirectTo('/departments/' . $department->uuid);
    }
}
