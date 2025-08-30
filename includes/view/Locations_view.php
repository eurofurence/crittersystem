<?php

use Engelsystem\Models\Location;
use Engelsystem\ShiftCalendarRenderer;
use Engelsystem\ShiftsFilterRenderer;

/**
 *
 * @param Location              $location
 * @param ShiftsFilterRenderer  $shiftsFilterRenderer
 * @param ShiftCalendarRenderer $shiftCalendarRenderer
 * @return string
 */
function location_view(Location $location, ShiftsFilterRenderer $shiftsFilterRenderer, ShiftCalendarRenderer $shiftCalendarRenderer)
{
    $user = auth()->user();

    $assignNotice = '';
    if (config('signup_requires_arrival') && !$user->state->arrived) {
        // Suppress arrival hint during allowed pre-/post-event signup windows
        $now = \Engelsystem\Helpers\Carbon::now();
        $buildupStart = config('buildup_start');
        $eventStart = config('event_start');
        $eventEnd = config('event_end');
        $teardownEnd = config('teardown_end');

        $withinPreEventWindow = !empty($buildupStart) && !empty($eventStart)
            && $now->greaterThanOrEqualTo($buildupStart)
            && $now->lessThan($eventStart);

        $withinPostEventWindow = !empty($eventEnd) && !empty($teardownEnd)
            && $now->greaterThan($eventEnd)
            && $now->lessThanOrEqualTo($teardownEnd);

        if (!($withinPreEventWindow || $withinPostEventWindow)) {
            $assignNotice = info(render_user_arrived_hint(), true);
        }
    }

    $description = '';
    if ($location->description) {
        $description = '<h3>' . __('general.description') . '</h3>';
        $parsedown = new Parsedown();
        $description .= $parsedown->parse(htmlspecialchars($location->description));
    }

    $neededAngelTypes = '';
    if (auth()->can('admin_shifts') && $location->neededAngelTypes->isNotEmpty()) {
        $neededAngelTypes .= '<h3>' . __('location.required_angels') . '</h3><ul>';
        foreach ($location->neededAngelTypes as $neededAngelType) {
            if ($neededAngelType->count) {
                $neededAngelTypes .= '<li><a href="'
                    . url('angeltypes', ['action' => 'view', 'angeltype_id' => $neededAngelType->angelType->id])
                    . '">' . $neededAngelType->angelType->name
                    . '</a>: '
                    . $neededAngelType->count
                    . '</li>';
            }
        }
        $neededAngelTypes .= '</ul>';
    }

    $dect = '';
    if (config('enable_dect') && $location->dect) {
        $dect = heading(__('Contact'), 3)
            . description([__('general.dect') => sprintf(
                '<a href="https://t.me/%s">%s%1$s</a>',
                str_replace('@', '', htmlspecialchars($location->dect)),
                config('policy')['telegram_visual_prefix']
            )]);
    }

    $staff_badge = '';
    if ($location->staff_only) {
        $staff_badge = '<span class="badge bg-primary">Staff Only</span>';
    }

    $tabs = [];
    if ($location->map_url) {
        $tabs[__('location.map_url')] = sprintf(
            '<div class="map">'
            . '<iframe style="width: 100%%; min-height: 75vh; border: 0 none;" src="%s"></iframe>'
            . '</div>',
            htmlspecialchars($location->map_url)
        );
    }

    $tabs[__('general.shifts')] = div('first', [
        $shiftsFilterRenderer->render(url('/locations', [
            'action'  => 'view',
            'location_id' => $location->id,
        ]), ['locations' => [$location->id]]),
        $shiftCalendarRenderer->render(),
    ]);

    $selected_tab = 0;
    $request = request();
    if ($request->has('shifts_filter_day')) {
        $selected_tab = count($tabs) - 1;
    }

    $link = button(url('/admin/locations'), icon('chevron-left'), 'btn-sm', '', __('general.back'));
    return page_with_title(
        (auth()->can('admin_locations') ? $link . ' ' : '') .
        icon('pin-map-fill') . htmlspecialchars($location->name) . ' ' . $staff_badge,
        [
        $assignNotice,
        auth()->can('admin_locations') ? buttons([
            button(
                url('/admin/locations/edit/' . $location->id),
                icon('pencil'),
                '',
                '',
                __('form.edit')
            ),
        ]) : '',
        $dect,
        $description,
        $neededAngelTypes,
        tabs($tabs, $selected_tab),
        ],
        true
    );
}

/**
 *
 * @param Location $location
 * @return string
 */
function location_name_render(Location $location)
{
    if (auth()->can('view_locations')) {
        return '<a href="' . location_link($location) . '">'
            . icon('pin-map-fill') . htmlspecialchars($location->name)
            . '</a>';
    }

    return icon('pin-map-fill') . htmlspecialchars($location->name);
}
