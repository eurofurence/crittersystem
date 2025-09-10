<?php

declare(strict_types=1);

namespace Engelsystem\Models\Department;

use Carbon\Carbon;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\BaseModel;
use Engelsystem\Models\Location;
use Engelsystem\Models\Shifts\Shift;
use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id               ID of the department
 * @property string $uuid             UUID of the department
 * @property string $name             Name of the department
 * @property string $slug             Slug of the department
 * @property string $description      Description of the department
 * @property bool   $staff_only       Whether the department is staff-only
 * @property Carbon|null $created_at  Creation timestamp
 * @property Carbon|null $updated_at  Last update timestamp
 *
 * @property-read Collection|User[] $users              Users belonging to the department
 * @property-read Collection|User[] $responsibles       Users responsible for the department
 * @property-read Collection|User[] $pendingUsers       Users with pending status
 * @property-read Collection|User[] $approvedUsers      Users with approved status
 * @property-read Collection|User[] $staffUsers         Approved users who are staff members
 * @property-read Collection|User[] $otherUsers         Approved users who are not staff members
 * @property-read Collection|Location[] $locations      Locations associated with the department
 * @property-read Collection|Shift[] $shifts            Shifts associated with the department
 * @property-read Collection|AngelType[] $angelTypes    Angel types associated with the department
 * @property-read Collection|DepartmentApplicationLog[] $applications Department applications
 */
class Department extends BaseModel
{
    use HasUuids;

//    protected $primaryKey = 'id';
    protected $keyType = 'integer'; // phpcs:ignore
    public $incrementing = true; // phpcs:ignore
    public $timestamps = true; // phpcs:ignore

//    public string $uuidColumn = 'uuid';

    /**
     * Get the columns that should receive a unique identifier.
     *
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [ // phpcs:ignore
        'name',
        'slug',
        'description',
        'staff_only',
    ];

    protected $casts = [ // phpcs:ignore
        'staff_only' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_responsibles')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_users')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'department_locations')
            ->withTimestamps();
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'department_shifts')
            ->withTimestamps();
    }

    public function angelTypes(): BelongsToMany
    {
        return $this->belongsToMany(AngelType::class, 'department_angel_types')
            ->withTimestamps();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(DepartmentApplicationLog::class);
    }

    public function pendingUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', 'pending');
    }

    public function approvedUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', 'approved');
    }

    public function staffUsers(): BelongsToMany
    {
        return $this->approvedUsers()
            ->whereHas('groups.privileges', function ($query): void {
                $query->orwhere('name', 'user.type.internal_staff')
                ->orWhere('name', 'user.type.staff')
                ->orWhere('name', 'user.type.admin');
            });
    }

    public function otherUsers(): BelongsToMany
    {
        return $this->approvedUsers()
            ->whereDoesntHave('groups.privileges', function ($query): void {
                $query->orwhere('name', 'user.type.internal_staff')
                    ->orWhere('name', 'user.type.staff')
                    ->orWhere('name', 'user.type.admin');
            });
    }
}
