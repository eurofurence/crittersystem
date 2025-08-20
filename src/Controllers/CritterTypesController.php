<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Http\Response;
use Engelsystem\Http\Request;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\UserAngelType;
//use Engelsystem\ShiftCalendarRenderer;
use Engelsystem\ShiftsFilter;
use Engelsystem\ShiftsFilterRenderer;
use Illuminate\Database\Query\JoinClause;

class CritterTypesController extends BaseController
{
    public function __construct(
        protected Response $response
    ) {
    }

    public function index(): Response
    {
        if (!auth()->can('angeltypes')) {
            return redirect(url('/'));
        }

        $user = auth()->user();
        $isStaffUser = $user->hasPermission('user.type.staff');
        $isShiftManager = $user->hasPermission('shifttypes.edit');

        // Load angel types together with user's membership info (pivot)
//        $angeltypes = AngelType::query()
//            ->select([
//                'angel_types.*',
//                'user_angel_type.id AS user_angel_type_id',
//                'user_angel_type.confirm_user_id',
//                'user_angel_type.supporter',
//            ])
//            ->leftJoin('user_angel_type', function (JoinClause $join) use ($user): void {
//                $join->on('angel_types.id', 'user_angel_type.angel_type_id');
//                $join->where('user_angel_type.user_id', $user->id);
//            })
//            ->get();

        // Load angel types together with user's membership info (pivot)
        $angeltypesQuery = AngelType::query()
            ->select([
                'angel_types.*',
                'user_angel_type.id AS user_angel_type_id',
                'user_angel_type.confirm_user_id',
                'user_angel_type.supporter',
            ])
            ->leftJoin('user_angel_type', function (JoinClause $join) use ($user): void {
                $join->on('angel_types.id', 'user_angel_type.angel_type_id');
                $join->where('user_angel_type.user_id', $user->id);
            });

        if (!$isStaffUser) {
            $angeltypesQuery->where('staff_only', false);
        }

            $angeltypes = $angeltypesQuery->get();

        // Compute simple presentation fields expected by the view
        $items = [];
        foreach ($angeltypes as $type) {
            $membership = '❌';
            if (!empty($type->user_angel_type_id)) {
                if ($type->restricted && empty($type->confirm_user_id)) {
                    $membership = __('❔ Unconfirmed');
                } elseif ($type->supporter) {
                    $membership = __('🐱‍👤 Supporter');
                } else {
                    $membership = __('✅ Member');
                }
            }

            $items[] = [
                'id' => $type->id,
                'name' => $type->name,
                'staffOnly' => (bool) $type->staff_only,
                'restricted' => (bool) $type->restricted,
                'shift_self_signup' => (bool) $type->shift_self_signup,
                'membership' => $membership,
                'user_angel_type_id' => $type->user_angel_type_id,
            ];
        }

        return $this->response->withView('pages/crittertypes/index', [
            'angeltypes' => $items,
            'isAdmin' => auth()->can('admin_angel_types'),
            'isStaffUser' => $isStaffUser,
            'isShiftManager' => $isShiftManager,
        ]);
    }

    public function show(Request $request): Response
    {
        if (!auth()->can('angeltypes')) {
            return redirect(url('/'));
        }

        $user = auth()->user();
        $angeltypeId = (int) $request->getAttribute('angeltype_id');
        $angeltype = AngelType::findOrFail($angeltypeId);

        /** @var UserAngelType|null $userAngelType */
        $userAngelType = UserAngelType::whereUserId($user->id)
            ->where('angel_type_id', $angeltype->id)
            ->first();

        $members = $angeltype->userAngelTypes
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->load(['state', 'personalData', 'contact']);

        // Prepare days list and shifts filter based on legacy helpers
        $days = $this->collectShiftDays($angeltype);
        $shiftsFilter = $this->buildShiftsFilter($angeltype, $days, $request);

        // Show both free and filled shifts by default to ensure visibility
        $shiftsFilter->setFilled([ShiftsFilter::FILLED_FREE, ShiftsFilter::FILLED_FILLED]);

        $shiftsFilterRenderer = new ShiftsFilterRenderer($shiftsFilter);
        $shiftsFilterRenderer->enableDaySelection($days);

        // Build base URL for shifts filter controls; ensure legacy links are rewritten to crittertypes
        $baseCritterUrl = url('/crittertypes/' . $angeltype->id . '?showShiftsTab=1');
        $controlsHtml = $shiftsFilterRenderer->render($baseCritterUrl, ['type' => $angeltype->id]);
        $legacyBase = url('/angeltypes', ['action' => 'view', 'angeltype_id' => $angeltype->id]);
        $controlsHtml = str_replace($legacyBase, $baseCritterUrl, $controlsHtml);

        // Use legacy helper to build calendar renderer
        $shiftCalendarRenderer = \shiftCalendarRendererByShiftFilter($shiftsFilter);

        // Determine selected tab
        $tab = ($request->has('shifts_filter_day') || $request->has('showShiftsTab')) ? 1 : 0;

        // Compute membership groupings for table display
        [$supporters, $membersConfirmed, $membersUnconfirmed] = $this->splitMembers($angeltype, $members);

        $isSupporter = $userAngelType?->supporter ?? false;

        return $this->response->withView('pages/crittertypes/show', [
            'angeltype' => $angeltype,
            'userAngelType' => $userAngelType,
            'isAdminAngelTypes' => auth()->can('admin_angel_types'),
            'isAdminUserAngelTypes' => auth()->can('admin_user_angeltypes') || $isSupporter,
            'isSupporter' => $isSupporter,
            'supporters' => $supporters,
            'membersConfirmed' => $membersConfirmed,
            'membersUnconfirmed' => $membersUnconfirmed,
            'shiftsFilterUrl' => url('/crittertypes/' . $angeltype->id . '?showShiftsTab=1'),
            'shiftsFilterControls' => $controlsHtml,
            'shiftCalendarHtml' => $shiftCalendarRenderer->render(),
            'tab' => $tab,
        ]);
    }

    /**
     * Collect days that have shifts for the given angeltype using legacy data helpers.
     * @return array<string, string> key: Y-m-d, value: formatted label
     */
    protected function collectShiftDays(AngelType $angeltype): array
    {
        $days = [];
        $all = \Shifts_by_angeltype($angeltype);
        foreach ($all as $shift) {
            $day = \Engelsystem\Helpers\Carbon::make($shift['start'])->format('Y-m-d');
            if (!isset($days[$day])) {
                $days[$day] = \dateWithEventDay($day);
            }
        }
        ksort($days);
        return $days;
    }

    protected function buildShiftsFilter(AngelType $angeltype, array $days, Request $request): ShiftsFilter
    {
        $locationIds = \Engelsystem\Models\Location::query()
            ->select('id')
            ->pluck('id')
            ->toArray();
        $filter = new ShiftsFilter(auth()->can('user_shifts_admin'), $locationIds, [$angeltype->id]);
        // Ensure selected types include this angeltype
        $filter->setTypes([$angeltype->id]);
        $selectedDay = date('Y-m-d');
        if (!empty($days) && !isset($days[$selectedDay])) {
            $selectedDay = array_key_first($days);
        }
        if ($request->get('shifts_filter_day')) {
            $selectedDay = (string) $request->get('shifts_filter_day');
        }
        $filter->setStartTime(\parse_date('Y-m-d H:i', $selectedDay . ' 00:00'));
        $filter->setEndTime(\parse_date('Y-m-d H:i', $selectedDay . ' 23:59'));
        return $filter;
    }

    protected function splitMembers(AngelType $angeltype, mixed $members): array
    {
        $supporters = [];
        $confirmed = [];
        $unconfirmed = [];
        foreach ($members as $member) {
            // Minimal dataset for rendering
            $entry = [
                'id' => $member->id,
                'name' => \User_Nick_render($member) . \User_Pronoun_render($member),
                'dect' => config('enable_dect') && $member->contact?->dect
                    ? sprintf(
                        '<a href="https://t.me/%s">%s%1$s</a>',
                        str_replace('@', '', htmlspecialchars((string) $member->contact->dect)),
                        config('policy')['telegram_visual_prefix']
                    )
                    : '',
                'pivot' => $member->pivot,
                'isStaff' => $member->hasAnyPermission([
                    'user.type.internal_staff',
                    'user.type.staff',
                    'user.type.admin',
                ]),
            ];

            if ($angeltype->restricted && empty($member->pivot->confirm_user_id)) {
                $unconfirmed[] = $entry;
            } elseif ($member->pivot->supporter) {
                $supporters[] = $entry;
            } else {
                $confirmed[] = $entry;
            }
        }
        return [$supporters, $confirmed, $unconfirmed];
    }

    // --- Temporary admin actions bridging to legacy implementation ---
    // TODO: Replace redirects with full Twig-based forms/controllers
    public function edit(Request $request): Response
    {
        $angeltypeId = (int) ($request->getAttribute('angeltype_id') ?? 0);
        $supporterMode = !auth()->can('admin_angel_types');

        if ($angeltypeId) {
            $angeltype = AngelType::findOrFail($angeltypeId);
            // Supporters can edit only if they are supporters of this angeltype or have admin_user_angeltypes
            if (
                $supporterMode && !auth()
                    ->user()?->isAngelTypeSupporter($angeltype) && !auth()->can('admin_user_angeltypes')
            ) {
                return redirect(url('/crittertypes'));
            }
        } else {
            if ($supporterMode) {
                return redirect(url('/crittertypes'));
            }
            $angeltype = new AngelType();
        }

        // Certifications list for admin mode
        $certifications = \Engelsystem\Models\Certification::where('is_active', true)->orderBy('title')->get();
        $requiredIds = $angeltype->exists ? $angeltype->requiredCertifications->pluck('id')->toArray() : [];

        return $this->response->withView('pages/crittertypes/edit', [
            'angeltype' => $angeltype,
            'supporterMode' => $supporterMode,
            'certifications' => $certifications,
            'requiredCertificationIds' => $requiredIds,
        ]);
    }

    public function save(Request $request): Response
    {
        $angeltypeId = (int) ($request->getAttribute('angeltype_id') ?? 0);
        $supporterMode = !auth()->can('admin_angel_types');

        if ($angeltypeId) {
            $angeltype = AngelType::findOrFail($angeltypeId);
            if (
                $supporterMode &&
                !auth()->user()?->isAngelTypeSupporter($angeltype) &&
                !auth()->can('admin_user_angeltypes')
            ) {
                return redirect(url('/crittertypes'));
            }
        } else {
            if ($supporterMode) {
                return redirect(url('/crittertypes'));
            }
            $angeltype = new AngelType();
        }

        // Basic validation/assignment (keep consistent with legacy)
        if (!$supporterMode) {
            if ($request->request->has('name')) {
                $name = substr((string) $request->request->get('name'), 0, 255);
                if ($name === '') {
                    error(__('Please check the name. Maybe it already exists.'));
                    return $this->edit($request);
                }
                // Ensure unique
                $exists = AngelType::whereName($name)->where('id', '!=', $angeltype->id)->exists();
                if ($exists) {
                    error(__('Please check the name. Maybe it already exists.'));
                    return $this->edit($request);
                }
                $angeltype->name = $name;
            }

            $angeltype->staff_only = $request->request->has('staff_only');
            $angeltype->restricted = $request->request->has('restricted');
            $angeltype->shift_self_signup = $request->request->has('shift_self_signup');
            $angeltype->show_on_dashboard = $request->request->has('show_on_dashboard');
            $angeltype->hide_register = $request->request->has('hide_register');
            $angeltype->hide_on_shift_view = $request->request->has('hide_on_shift_view');
        }

        $angeltype->description = (string) $request->request->get('description', $angeltype->description);
        $angeltype->contact_name = (string) $request->request->get('contact_name', $angeltype->contact_name);
        $angeltype->contact_dect = (string) ($request->request->get('contact_dect') ?? '');
        $angeltype->contact_email = (string) $request->request->get('contact_email', $angeltype->contact_email);

        $angeltype->save();

        // Certifications requirements for admins only
        if (!$supporterMode) {
            $newReq = $request->request->all('certification_requirements');
            if (!is_array($newReq)) {
                $newReq = [];
            }
            $newReq = array_map('intval', $newReq);
            if (!empty($newReq)) {
                $valid = \Engelsystem\Models\Certification::whereIn('id', $newReq)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();
                $newReq = array_values(array_intersect($newReq, $valid));
            }
            $angeltype->requiredCertifications()->sync($newReq);
        }

        success(__('Critter type saved.'));
        engelsystem_log('Saved angel type: ' . $angeltype->name);
        return redirect(url('/crittertypes/' . $angeltype->id));
    }

    public function deleteConfirm(Request $request): Response
    {
        $angeltypeId = (int) $request->getAttribute('angeltype_id');
        if (!auth()->can('admin_angel_types')) {
            return redirect(url('/crittertypes/' . $angeltypeId));
        }
        $angeltype = AngelType::findOrFail($angeltypeId);
        return $this->response->withView('pages/crittertypes/delete', [
            'angeltype' => $angeltype,
        ]);
    }

    public function delete(Request $request): Response
    {
        $angeltypeId = (int) $request->getAttribute('angeltype_id');
        if (!auth()->can('admin_angel_types')) {
            return redirect(url('/crittertypes/' . $angeltypeId));
        }
        $angeltype = AngelType::findOrFail($angeltypeId);
        $angeltypeName = $angeltype->name;
        $angeltype->delete();
        engelsystem_log('Deleted critter type: ' . $angeltypeName);
        success(sprintf(__('Critter type %s deleted.'), $angeltypeName));
        return redirect(url('/crittertypes'));
    }
}
