<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Api;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\Location;
use Engelsystem\Models\Shifts\Shift;
use Engelsystem\Models\Shifts\ShiftType;
use Engelsystem\Models\Shifts\ShiftEntry;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\Shifts\NeededAngelType;
use Engelsystem\Models\Shifts\ScheduleShift;
use Engelsystem\Services\CertificationService;
use Illuminate\Support\Carbon;
use Engelsystem\Models\Worklog;
use Engelsystem\Models\User\User;
use Engelsystem\Models\UserAngelType;

class ShiftManagerV2Controller extends BaseController
{
    /**
     * Minimal read-only access requires user_shifts.
     * Persona-specific filtering will be applied client-side in early milestone.
     */
    protected array $permissions = [
        'user_shifts',
    ];

    public function __construct(
        protected Authenticator $auth,
        protected Response $response,
        protected CertificationService $certifications,
    ) {
    }

    /**
     * POST /api/v2/shift-manager/apply
     * Body: { shift_id: int, angel_type_id?: int }
     * Note: Scaffolding placeholder — real DB write will follow in the next iteration.
     */
    public function apply(Request $request): Response
    {
        // Staff user applies for themselves to a shift.
        $user = $this->auth->user();
        if (!$user) {
            return $this->response->withJson(['ok' => false, 'error' => 'unauthenticated']);
        }

        $shiftId = (int) ($request->request->get('shift_id') ?? 0);
        $angelTypeId = $request->request->get('angel_type_id');

        if ($shiftId <= 0) {
            return $this->response->withJson(['ok' => false, 'error' => 'invalid_shift_id']);
        }

        // Validate shift exists and is applicable
        /** @var Shift|null $shift */
        $shift = Shift::find($shiftId);
        if (!$shift) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_not_found']);
        }
        if ($shift->end->isPast()) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_in_past']);
        }

        // Prevent duplicate signup
        $already = ShiftEntry::query()
            ->where('shift_id', $shiftId)
            ->where('user_id', $user->id)
            ->exists();
        if ($already) {
            return $this->response->withJson(['ok' => false, 'error' => 'already_assigned']);
        }

        // Overlap guard with user's other assignments
        $overlap = $user->shiftEntries()
            ->whereHas('shift', function ($q) use ($shift): void {
                $q->where('end', '>', $shift->start)->where('start', '<', $shift->end);
            })
            ->exists();
        if ($overlap) {
            return $this->response->withJson(['ok' => false, 'error' => 'overlap']);
        }

        // Determine angel type to assign
        $needed = NeededAngelType::query()->where('shift_id', $shiftId)->get();
        if ($needed->isEmpty()) {
            return $this->response->withJson(['ok' => false, 'error' => 'no_needed_types']);
        }

        $targetAngelTypeId = $angelTypeId ? (int) $angelTypeId : null;
        $chosenAngelTypeId = null;

        foreach ($needed as $n) {
            $typeId = (int) $n->angel_type_id;
            if ($targetAngelTypeId && $typeId !== $targetAngelTypeId) {
                continue;
            }
            $assigned = ShiftEntry::query()
                ->where('shift_id', $shiftId)
                ->where('angel_type_id', $typeId)
                ->count();
            $required = (int) $n->count;
            if ($required > 0 && $assigned >= $required) {
                continue; // capacity full for this type
            }
            // Certification eligibility check
            $angelType = AngelType::find($typeId);
            if ($angelType) {
                $req = $this->certifications->checkUserCertificationRequirements($user, $angelType);
                if ($req['meets_requirements'] !== true) {
                    continue;
                }
            }
            $chosenAngelTypeId = $typeId;
            break;
        }

        if (!$chosenAngelTypeId) {
            return $this->response->withJson(['ok' => false, 'error' => 'no_eligible_slot']);
        }

        // Create the assignment
        $entry = ShiftEntry::create([
            'shift_id' => $shiftId,
            'angel_type_id' => $chosenAngelTypeId,
            'user_id' => $user->id,
            'freeloaded' => false,
            'user_comment' => '',
        ]);

        return $this->response->withJson([
            'ok' => true,
            'shift_entry_id' => (int) $entry->id,
            'shift_id' => $shiftId,
            'angel_type_id' => $chosenAngelTypeId,
        ]);
    }

    /**
     * POST /api/v2/shift-manager/cancel
     * Body: { shift_entry_id: int }
     * Note: Scaffolding placeholder — real DB write will follow in the next iteration.
     */
    public function cancel(Request $request): Response
    {
        // Alias for user self-cancel; managers should use unassign, but we allow manager here too.
        $user = $this->auth->user();
        if (!$user) {
            return $this->response->withJson(['ok' => false, 'error' => 'unauthenticated']);
        }

        $entryId = (int) ($request->request->get('shift_entry_id') ?? 0);
        if ($entryId <= 0) {
            return $this->response->withJson(['ok' => false, 'error' => 'invalid_shift_entry_id']);
        }

        /** @var ShiftEntry|null $entry */
        $entry = ShiftEntry::query()->with('shift')->find($entryId);
        if (!$entry) {
            return $this->response->withJson(['ok' => false, 'error' => 'entry_not_found']);
        }

        $isOwner = (int) $entry->user_id === (int) $user->id;
        $isManager = $this->auth->can('admin_shifts');
        if (!$isOwner && !$isManager) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }

        $shift = $entry->shift;
        if (!$shift) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_not_found']);
        }
        if ($shift->end->isPast()) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_in_past']);
        }

        // Respect unsubscribe window if configured (hours before start).
        // Managers bypass this only if they have admin_shifts.
        $limitHours = (int) (config('last_unsubscribe') ?? 0);
        if ($limitHours > 0 && !$isManager) {
            $limitTs = $shift->start->copy()->subHours($limitHours);
            if (Carbon::now()->greaterThanOrEqualTo($limitTs)) {
                return $this->response->withJson([
                    'ok' => false,
                    'error' => 'unsubscribe_window_closed',
                ]);
            }
        }

        $entry->delete();

        return $this->response->withJson([
            'ok' => true,
            'shift_entry_id' => $entryId,
            'shift_id' => (int) $shift->id,
        ]);
    }

    /**
     * GET /api/v2/shift-manager/shifts
     * Query params:
     *  - date: YYYY-MM-DD (defaults to today)
     *  - locations[]: optional array of location ids
     *  - start: HH:mm optional (default 00:00)
     *  - end: HH:mm optional (default 23:59)
     *  - q: search text (title/type/location)
     */
    public function assign(Request $request): Response
    {
        // Manager assigns user_ids[] to a shift under a specific angel_type_id
        if (!$this->auth->can('admin_shifts')) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }
        $shiftId = (int) ($request->request->get('shift_id') ?? 0);
        $angelTypeId = (int) ($request->request->get('angel_type_id') ?? 0);
        /** @var array<int|string>|null $userIds */
        $userIds = $request->request->all('user_ids');
        if (empty($userIds)) {
            // Fallback: support single string comma-separated 'user_ids'
            $raw = (string) ($request->request->get('user_ids') ?? '');
            if (!empty($raw)) {
                $userIds = preg_split('/[,\s]+/', $raw) ?: [];
            }
        }
        if ($shiftId <= 0 || $angelTypeId <= 0 || empty($userIds)) {
            return $this->response->withJson(['ok' => false, 'error' => 'invalid_input']);
        }
        /** @var Shift|null $shift */
        $shift = Shift::find($shiftId);
        if (!$shift) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_not_found']);
        }
        if ($shift->end->isPast()) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_in_past']);
        }
        $needed = NeededAngelType::query()
            ->where('shift_id', $shiftId)
            ->where('angel_type_id', $angelTypeId)
            ->first();

        $created = [];
        $addedCount = 0;
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) {
                continue;
            }
            // Skip duplicates
            $exists = ShiftEntry::query()
                ->where('shift_id', $shiftId)
                ->where('user_id', $uid)
                ->exists();
            if ($exists) {
                continue;
            }
            // Overlap guard — skip users that would conflict
            $overlap = ShiftEntry::query()
                ->where('user_id', $uid)
                ->whereHas('shift', function ($q) use ($shift): void {
                    $q->where('end', '>', $shift->start)->where('start', '<', $shift->end);
                })
                ->exists();
            if ($overlap) {
                continue;
            }
            // Managers are allowed to assign regardless of missing certifications.
            $entry = ShiftEntry::create([
                'shift_id' => $shiftId,
                'angel_type_id' => $angelTypeId,
                'user_id' => $uid,
                'freeloaded' => false,
                'user_comment' => '',
            ]);
            $created[] = (int) $entry->id;
            $addedCount++;
        }

        // Adjust NeededAngelType record as requested:
        // if missing, create with count = added; if exists, increase count by added
        if ($addedCount > 0) {
            if ($needed) {
                $needed->count = (int) $needed->count + $addedCount;
                $needed->save();
            } else {
                NeededAngelType::create([
                    'shift_id' => $shiftId,
                    'angel_type_id' => $angelTypeId,
                    'count' => $addedCount,
                    'restricted' => false,
                ]);
            }
            return $this->response->withJson(['ok' => true, 'created' => $created, 'added' => $addedCount]);
        }

        // Nothing added — report back to UI with an error so it doesn't show a misleading success.
        return $this->response->withJson(['ok' => false, 'error' => 'no_users_added']);
    }

    public function unassign(Request $request): Response
    {
        // Manager removes an assignment by entry id
        if (!$this->auth->can('admin_shifts')) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }
        $entryId = (int) ($request->request->get('shift_entry_id') ?? 0);
        if ($entryId <= 0) {
            return $this->response->withJson(['ok' => false, 'error' => 'invalid_shift_entry_id']);
        }
        /** @var ShiftEntry|null $entry */
        $entry = ShiftEntry::query()->with('shift')->find($entryId);
        if (!$entry) {
            return $this->response->withJson(['ok' => false, 'error' => 'entry_not_found']);
        }
        $shift = $entry->shift;
        if (!$shift) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_not_found']);
        }
        $entry->delete();
        return $this->response->withJson(['ok' => true]);
    }

    public function worklog(Request $request): Response
    {
        // Manager logs extra hours for user on a shift
        if (!$this->auth->can('admin_shifts')) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }
        $userId = (int) ($request->request->get('user_id') ?? 0);
        $minutes = (int) ($request->request->get('minutes') ?? 0);
        $start = $request->request->get('start');
        $end = $request->request->get('end');
        $comment = trim((string) ($request->request->get('comment') ?? ''));
        if ($userId <= 0) {
            return $this->response->withJson(['ok' => false, 'error' => 'invalid_input']);
        }
        if ($comment === '') {
            return $this->response->withJson(['ok' => false, 'error' => 'comment_required']);
        }
        $hours = 0.0;
        if ($minutes > 0) {
            $hours = round($minutes / 60.0, 2);
        } elseif ($start && $end) {
            try {
                $s = \Carbon\Carbon::parse($start);
                $e = \Carbon\Carbon::parse($end);
                if ($e->greaterThan($s)) {
                    $hours = round($e->floatDiffInMinutes($s) / 60.0, 2);
                }
            } catch (\Exception) {
                return $this->response->withJson(['ok' => false, 'error' => 'invalid_time']);
            }
        } else {
            return $this->response->withJson(['ok' => false, 'error' => 'invalid_time']);
        }
        $creatorId = $this->auth->user()?->id ?? null;
        $log = Worklog::create([
            'user_id' => $userId,
            'creator_id' => $creatorId,
            'hours' => $hours,
            'comment' => $comment,
            'worked_at' => Carbon::now(),
        ]);
        return $this->response->withJson(['ok' => true, 'worklog_id' => (int) $log->id, 'hours' => (float) $hours]);
    }

    public function noshow(Request $request): Response
    {
        // Manager toggles no-show (freeloader) on a shift entry
        if (!$this->auth->can('admin_shifts')) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }
        $entryId = (int) ($request->request->get('shift_entry_id') ?? 0);
        $flag = $request->request->get('freeloaded');
        if ($entryId <= 0) {
            return $this->response->withJson(['ok' => false, 'error' => 'invalid_shift_entry_id']);
        }
        /** @var ShiftEntry|null $entry */
        $entry = ShiftEntry::find($entryId);
        if (!$entry) {
            return $this->response->withJson(['ok' => false, 'error' => 'entry_not_found']);
        }
        $new = is_null($flag) ? !$entry->freeloaded : (bool) $flag;
        $entry->freeloaded = $new;
        $entry->save();
        return $this->response->withJson(
            [
            'ok' => true,
            'shift_entry_id' => (int) $entry->id,
            'freeloaded' => (bool) $new,
            ],
        );
    }

    public function shift(Request $request): Response
    {
        // Return detailed shift info for manager (assignments, needed types)
        if (!$this->auth->can('admin_shifts')) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }
        $id = (int) $request->getAttribute('id');
        /** @var Shift|null $shift */
        $shift = Shift::query()->with(['shiftEntries.user', 'location', 'shiftType'])->find($id);
        if (!$shift) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_not_found']);
        }
        $needed = NeededAngelType::query()->where('shift_id', $shift->id)->get(['angel_type_id', 'count']);
        return $this->response->withJson([
            'ok' => true,
            'shift' => [
                'id' => (int) $shift->id,
                'title' => (string) ($shift->title ?: $shift->shiftType?->name ?: ''),
                'location' => (string) ($shift->location?->name ?: ''),
                'start_ts' => $shift->start->getTimestamp(),
                'end_ts' => $shift->end->getTimestamp(),
            ],
            'needed' => $needed->map(fn($n) => [
                'angel_type_id' => (int) $n->angel_type_id,
                'count' => (int) $n->count])->values(),
            'assignments' => $shift->shiftEntries->map(function (ShiftEntry $e) {
                return [
                    'entry_id' => (int) $e->id,
                    'user_id' => (int) $e->user_id,
                    'user_name' => (string) ($e->user?->name ?: ''),
                    'angel_type_id' => (int) $e->angel_type_id,
                    'freeloaded' => (bool) $e->freeloaded,
                ];
            })->values(),
        ]);
    }

    public function users(Request $request): Response
    {
        if (!$this->auth->can('admin_shifts')) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }
        $q = (string) $request->query->get('q', '');
        $users = User::query()
            ->when($q !== '', function ($qb) use ($q): void {
                $qb->where('name', 'like', '%' . $q . '%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);
        return $this->response->withJson([
            'ok' => true,
            'users' => $users->map(fn($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])->values(),
        ]);
    }

    public function shifts(Request $request): Response
    {
        $date = $request->query->get('date');
        $startS = $request->query->get('start', '00:00');
        $endS = $request->query->get('end', '23:59');
        $q = (string) $request->query->get('q', '');
        /** @var array<int|string>|null $locationFilter */
        $locationFilter = $request->query->all('locations');
        /** @var array<int|string>|null $shiftTypeFilter */
        $shiftTypeFilter = $request->query->all('shift_types');
        /** @var array<int|string>|null $critterTypeFilter */
        $critterTypeFilter = $request->query->all('critter_types');

        $day = $date ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay() : Carbon::today();
        $start = Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d') . ' ' . $startS);
        $end = Carbon::createFromFormat('Y-m-d H:i', $day->format('Y-m-d') . ' ' . $endS);

        // Always provide the full location list (up to 30) so filters remain usable
        $locations = Location::query()
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name']);
        $shiftTypes = ShiftType::query()->orderBy('name')->get(['id', 'name']);
        $critterTypes = AngelType::query()->orderBy('name')->get(['id', 'name']);

        $shiftsQuery = Shift::query()
            ->with(['location', 'shiftType', 'shiftEntries'])
            ->whereDate('start', $day->toDateString())
            ->where(function ($q2) use ($start, $end): void {
                // Include shifts that intersect the time window
                $q2->where('end', '>', $start)->where('start', '<', $end);
            });

        if ($locationFilter) {
            $shiftsQuery->whereIn('location_id', $locationFilter);
        }
        if ($shiftTypeFilter) {
            $shiftsQuery->whereIn('shift_type_id', $shiftTypeFilter);
        }
        if ($critterTypeFilter) {
            // Only match shifts that require at least one of the selected critter types
            $shiftsQuery->whereExists(function ($sub) use ($critterTypeFilter): void {
                $sub->from('needed_angel_types')
                    ->selectRaw('1')
                    ->whereColumn('needed_angel_types.shift_id', 'shifts.id')
                    ->whereIn('needed_angel_types.angel_type_id', $critterTypeFilter)
                    ->limit(1);
            });
        }

        if ($q !== '') {
            $shiftsQuery->where(function ($q2) use ($q): void {
                $q2->where('title', 'like', '%' . $q . '%')
                    ->orWhereHas('shiftType', function ($q3) use ($q): void {
                        $q3->where('name', 'like', '%' . $q . '%');
                    })
                    ->orWhereHas('location', function ($q3) use ($q): void {
                        $q3->where('name', 'like', '%' . $q . '%');
                    });
            });
        }

        $shifts = $shiftsQuery->limit(1000)->get();

        // Collect current user's assigned shifts for overlap detection
        $user = $this->auth->user();
        $myIntervals = [];
        $myEntriesByShift = [];
        if ($user) {
            // Prepare overlap intervals and map of my entries by shift
            $entries = $user->shiftEntries()
                ->with('shift')
                ->whereHas('shift', function ($q) use ($start, $end): void {
                    $q->where('end', '>', $start)->where('start', '<', $end);
                })
                ->get();
            foreach ($entries as $e) {
                if ($e->shift) {
                    $myIntervals[] = [
                        'start' => $e->shift->start->getTimestamp(),
                        'end' => $e->shift->end->getTimestamp(),
                    ];
                    $myEntriesByShift[(int) $e->shift_id] = (int) $e->id;
                }
            }
        }

        $overlapsWithMe = function (Shift $s) use ($myIntervals): bool {
            $sStart = $s->start->getTimestamp();
            $sEnd = $s->end->getTimestamp();
            foreach ($myIntervals as $iv) {
                // overlap iff not (end <= other.start or start >= other.end)
                if (!($sEnd <= $iv['start'] || $sStart >= $iv['end'])) {
                    return true;
                }
            }
            return false;
        };

        // Helper to compute required with schedule fallback
        $computeRequired = function (Shift $s): int {
            // 1) Direct needed by shift
            $direct = (int) (NeededAngelType::query()->where('shift_id', $s->id)->sum('count') ?? 0);
            if ($direct > 0) {
                return $direct;
            }
            // 2) Fallback via schedule (if any)
            /** @var ScheduleShift|null $ss */
            $ss = ScheduleShift::query()->where('shift_id', $s->id)->first();
            if ($ss) {
                // Load schedule flag via relation
                $schedule = $ss->schedule()->first(['needed_from_shift_type']);
                $neededFromType = (bool) ($schedule?->needed_from_shift_type ?? false);
                if ($neededFromType) {
                    // Required from shift type
                    return (int) (NeededAngelType::query()
                        ->where('shift_type_id', $s->shift_type_id)
                        ->sum('count') ?? 0);
                }
                // Required from location
                return (int) (NeededAngelType::query()
                    ->where('location_id', $s->location_id)
                    ->sum('count') ?? 0);
            }
            return 0;
        };

        // KPI aggregation
        $assignedSum = 0;
        $requiredSum = 0;
        $emptyShifts = 0;
        $openPositions = 0;

        foreach ($shifts as $s) {
            $assigned = $s->shiftEntries->count();
            $required = $computeRequired($s);
            $assignedSum += $assigned;
            $requiredSum += $required;
            if ($assigned === 0) {
                $emptyShifts++;
            }
            if ($required > $assigned) {
                $openPositions += ($required - $assigned);
            }
        }

        $result = [
            'date' => $day->toDateString(),
            'locations' => $locations->map(fn($l) => [
                'id' => (int) $l->id,
                'name' => (string) $l->name,
            ])->values(),
            'shift_types' => $shiftTypes->map(fn($t) => [
                'id' => (int) $t->id,
                'name' => (string) $t->name,
            ])->values(),
            'critter_types' => $critterTypes->map(fn($t) => [
                'id' => (int) $t->id,
                'name' => (string) $t->name,
            ])->values(),
            'kpis' => [
                'shifts_today' => (int) $shifts->count(),
                'open_positions' => (int) $openPositions,
                'fill_rate' => $requiredSum > 0 ? ($assignedSum / $requiredSum) : null,
                'empty_shifts' => (int) $emptyShifts,
            ],
            'shifts' => $shifts->map(function (Shift $s) use ($computeRequired, $overlapsWithMe, $myEntriesByShift) {
                $assigned = $s->shiftEntries->count();
                $required = $computeRequired($s);
                $status = 'green';
                if ($assigned === 0) {
                    $status = 'red';
                } elseif ($required > 0 && $assigned < $required) {
                    $status = 'yellow';
                }
                $capacityFull = $required > 0 && $assigned >= $required;
                $overlaps = $overlapsWithMe($s);

                // Certification gating + eligible angel types for current user
                $needsCert = false;
                $eligibleAngelTypes = [];
                $user = $this->auth->user();
                if ($user) {
                    // Collect needed angel types with schedule fallback
                    $neededRows = NeededAngelType::query()
                        ->where('shift_id', $s->id)
                        ->get(['angel_type_id', 'count']);
                    if ($neededRows->isEmpty()) {
                        $ss = ScheduleShift::query()->where('shift_id', $s->id)->first();
                        if ($ss) {
                            $schedule = $ss->schedule()->first(['needed_from_shift_type']);
                            $neededFromType = (bool) ($schedule?->needed_from_shift_type ?? false);
                            if ($neededFromType) {
                                $neededRows = NeededAngelType::query()
                                    ->where('shift_type_id', $s->shift_type_id)
                                    ->get(['angel_type_id', 'count']);
                            } else {
                                $neededRows = NeededAngelType::query()
                                    ->where('location_id', $s->location_id)
                                    ->get(['angel_type_id', 'count']);
                            }
                        }
                    }
                    if ($neededRows->isNotEmpty()) {
                        $neededIds = $neededRows->pluck('angel_type_id')->all();
                        $angelTypes = AngelType::query()->whereIn('id', $neededIds)->get(['id', 'name']);
                        foreach ($angelTypes as $angelType) {
                            $taken = $s->shiftEntries->where('angel_type_id', (int) $angelType->id)->count();
                            $requiredForType = (int) (
                                $neededRows->firstWhere(
                                    'angel_type_id',
                                    (int) $angelType->id
                                )?->count ?? 0)
                            ;
                            $typeCapacityFull = $requiredForType > 0 && $taken >= $requiredForType;
                            if ($typeCapacityFull) {
                                continue;
                            }
                            $req = $this->certifications->checkUserCertificationRequirements($user, $angelType);
                            if ($req['meets_requirements'] === true) {
                                $eligibleAngelTypes[] = [
                                    'id' => (int) $angelType->id,
                                    'name' => (string) $angelType->name,
                                ];
                            }
                        }
                        $needsCert = empty($eligibleAngelTypes);
                    }
                }

                $canApply = !$capacityFull && !$overlaps && !$needsCert && ($s->end->isFuture());
                return [
                    'id' => (int) $s->id,
                    'title' => (string) ($s->title ?: $s->shiftType?->name ?: ''),
                    'type' => (string) ($s->shiftType?->name ?: ''),
                    'location_id' => (int) $s->location_id,
                    'location' => (string) ($s->location?->name ?: ''),
                    'start_ts' => $s->start->getTimestamp(),
                    'end_ts' => $s->end->getTimestamp(),
                    'required' => (int) $required,
                    'assigned' => (int) $assigned,
                    'status' => $status,
                    'eligibility' => [
                        'can_apply' => (bool) $canApply,
                        'capacity_full' => (bool) $capacityFull,
                        'overlaps' => (bool) $overlaps,
                        'needs_cert' => (bool) $needsCert,
                    ],
                    'eligible_angel_types' => $eligibleAngelTypes,
                    'is_assigned' => array_key_exists((int) $s->id, $myEntriesByShift),
                    'my_entry_id' => $myEntriesByShift[(int) $s->id] ?? null,
                    'can_cancel' => (function () use ($s) {
                        $limitHours = (int) (config('last_unsubscribe') ?? 0);
                        if ($limitHours <= 0) {
                            return true;
                        }
                        $limitTs = $s->start->copy()->subHours($limitHours);
                        return \Illuminate\Support\Carbon::now()->lessThan($limitTs);
                    })(),
                ];
            })->values(),
        ];

        return $this->response->withJson($result);
    }

    /**
     * GET /api/v2/shift-manager/dates
     * Returns available dates that have shifts
     */
    public function dates(Request $request): Response
    {
        // Get dates that have shifts, up to 30 days from today
        $today = Carbon::today();
        $endDate = $today->copy()->addDays(30);

        $dates = Shift::query()
            ->selectRaw('DATE(start) as shift_date')
            ->whereBetween('start', [$today, $endDate])
            ->groupBy('shift_date')
            ->orderBy('shift_date')
            ->limit(30)
            ->pluck('shift_date');

        $availableDates = $dates->map(function ($date) {
            $carbon = Carbon::parse($date);
            return [
                'date' => $carbon->format('Y-m-d'),
                'weekday' => $carbon->format('D'),
                'day' => $carbon->format('j'),
                'display' => $carbon->format('D j'), // e.g., "Wed 18"
            ];
        });

        return $this->response->withJson([
            'ok' => true,
            'dates' => $availableDates->values(),
            'today' => $today->format('Y-m-d'),
        ]);
    }

    /**
     * GET /api/v2/shift-manager/applications/{shift_id}
     * Returns pending applications for critter types needed by the shift
     */
    public function applications(Request $request, int $shiftId): Response
    {
        $user = $this->auth->user();

        // Only managers and supporters can view applications
        $isManager = $this->auth->can('admin_shifts');
        if (!$isManager && !$this->auth->can('user.type.staff')) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }

        $shift = Shift::query()->with(['location', 'shiftType'])->find($shiftId);
        if (!$shift) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_not_found']);
        }

        // Get needed angel types for this shift
        $neededTypes = NeededAngelType::query()
            ->where('shift_id', $shift->id)
            ->pluck('angel_type_id')
            ->toArray();

        // If no needed types on shift, check schedule fallback
        if (empty($neededTypes)) {
            $scheduleTypes = ScheduleShift::query()
                ->where('shift_type_id', $shift->shift_type_id)
                ->where('location_id', $shift->location_id)
                ->pluck('angel_type_id')
                ->toArray();
            $neededTypes = array_merge($neededTypes, $scheduleTypes);
        }

        if (empty($neededTypes)) {
            return $this->response->withJson([
                'ok' => true,
                'applications' => [],
                'angel_types' => [],
            ]);
        }

        // Get angel types info
        $angelTypes = AngelType::query()
            ->whereIn('id', $neededTypes)
            ->get(['id', 'name', 'restricted'])
            ->keyBy('id');

        // Filter by supporter permissions if not manager
        if (!$isManager) {
            $supportedTypes = UserAngelType::query()
                ->where('user_id', $user->id)
                ->whereIn('angel_type_id', $neededTypes)
                ->whereNotNull('supporter')
                ->pluck('angel_type_id')
                ->toArray();

            if (empty($supportedTypes)) {
                return $this->response->withJson([
                    'ok' => true,
                    'applications' => [],
                    'angel_types' => [],
                ]);
            }

            $neededTypes = $supportedTypes;
            $angelTypes = $angelTypes->whereIn('id', $neededTypes);
        }

        // Get pending applications (users who are members but not confirmed)
        $pendingApplications = UserAngelType::query()
            ->with('user:id,name')
            ->whereIn('angel_type_id', $neededTypes)
            ->whereNull('confirm_user_id')
            ->get();

        $applications = $pendingApplications->map(function ($userAngelType) {
            return [
                'user_id' => (int) $userAngelType->user_id,
                'user_name' => (string) $userAngelType->user->name,
                'angel_type_id' => (int) $userAngelType->angel_type_id,
                'created_at' => $userAngelType->created_at?->toISOString(),
            ];
        })->values();

        return $this->response->withJson([
            'ok' => true,
            'applications' => $applications,
            'angel_types' => $angelTypes->map(fn($at) => [
                'id' => (int) $at->id,
                'name' => (string) $at->name,
                'restricted' => (bool) $at->restricted,
            ])->values(),
        ]);
    }

    /**
     * POST /api/v2/shift-manager/applications/approve
     * Body: { items: [{ user_id: int, angel_type_id: int }], shift_id: int }
     */
    public function approveApplications(Request $request): Response
    {
        $user = $this->auth->user();

        // Only managers and supporters can approve applications
        $isManager = $this->auth->can('admin_shifts');
        if (!$isManager && !$this->auth->can('user.type.staff')) {
            return $this->response->withJson(['ok' => false, 'error' => 'forbidden']);
        }

        $items = (array) $request->getParsedBody()['items'] ?? [];
        $shiftId = (int) ($request->getParsedBody()['shift_id'] ?? 0);

        if (empty($items) || !$shiftId) {
            return $this->response->withJson(['ok' => false, 'error' => 'invalid_request']);
        }

        $shift = Shift::find($shiftId);
        if (!$shift) {
            return $this->response->withJson(['ok' => false, 'error' => 'shift_not_found']);
        }

        $approved = 0;
        $errors = [];

        foreach ($items as $item) {
            $userId = (int) ($item['user_id'] ?? 0);
            $angelTypeId = (int) ($item['angel_type_id'] ?? 0);

            if (!$userId || !$angelTypeId) {
                $errors[] = 'Invalid user or angel type ID';
                continue;
            }

            // Check if user is supporter for this angel type (if not manager)
            if (!$isManager) {
                $isSupporter = UserAngelType::query()
                    ->where('user_id', $user->id)
                    ->where('angel_type_id', $angelTypeId)
                    ->whereNotNull('supporter')
                    ->exists();

                if (!$isSupporter) {
                    $errors[] = 'Not authorized to approve for angel type ' . $angelTypeId;
                    continue;
                }
            }

            // Update the user angel type to set confirm_user_id
            $updated = UserAngelType::query()
                ->where('user_id', $userId)
                ->where('angel_type_id', $angelTypeId)
                ->whereNull('confirm_user_id')
                ->update(['confirm_user_id' => $user->id]);

            if ($updated > 0) {
                $approved++;

                // Log the approval
                error_log(sprintf(
                    'ShiftManagerV2: User %d approved user %d for angel type %d in shift %d',
                    $user->id,
                    $userId,
                    $angelTypeId,
                    $shiftId
                ));
            }
        }

        return $this->response->withJson([
            'ok' => true,
            'approved' => $approved,
            'errors' => $errors,
        ]);
    }
}
