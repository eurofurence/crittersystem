<?php

use Engelsystem\Models\Location;
use Engelsystem\Models\Question;
use Engelsystem\UserHintsRenderer;
use Illuminate\Support\Str;

/**
 * Render the user hints
 *
 * @return string
 */
function header_render_hints()
{
    $user = auth()->user();

    if ($user) {
        $hints_renderer = new UserHintsRenderer();

        $hints_renderer->addHint(admin_new_questions());
        $hints_renderer->addHint(user_angeltypes_unconfirmed_hint());
        $hints_renderer->addHint(render_user_departure_date_hint());
        $hints_renderer->addHint(user_driver_license_required_hint());
        $hints_renderer->addHint(user_ifsg_certificate_required_hint());

        // Important hints:
        $hints_renderer->addHint(render_user_freeloader_hint(), true);
        $hints_renderer->addHint(render_user_arrived_hint(true), true);
        $hints_renderer->addHint(render_user_pronoun_hint(), true);
        $hints_renderer->addHint(render_user_firstname_hint(), true);
        $hints_renderer->addHint(render_user_lastname_hint(), true);
        $hints_renderer->addHint(render_user_goodie_hint(), true);
        $hints_renderer->addHint(render_user_dect_hint(), true);
        $hints_renderer->addHint(render_user_mobile_hint(), true);

        return $hints_renderer->render();
    }

    return '';
}

/**
 * Returns the path of the current path with underscores instead of hyphens
 *
 * @return string
 */
function current_page()
{
    return request()->query->get('p') ?: str_replace('-', '_', request()->path());
}

/**
 * @return string
 */
function make_navigation()
{
    $page = current_page();
    $menu = [];
    $pages = [
        'news'                     => [__('news.title'), 'news'],
//        'meetings'               => [__('news.title.meetings'), 'user_meetings'],
        'user_shifts'              => [__('general.shifts'), 'user_shifts'],
        'user/certifications'      => [__('certifications.title'), 'certificates.view'],
        'questions'                => [__('Ask the Info Desk'), 'question.add'],
        'crittertypes'             => [__('angeltypes.angeltypes'), 'angeltypes'],
        'departments'              => [__('departments.title.plural'), 'dept.view'],
    ];

    foreach ($pages as $menu_page => $options) {
        if (!menu_is_allowed(permissions: $options)) {
            continue;
        }

        $title = ((array) $options)[0];
        $menu[] = toolbar_item_link(
            url(str_replace('_', '-', $menu_page)),
            '',
            $title,
            $menu_page == $page
        );
    }

    $menu = make_location_navigation($menu);

    $admin_menu = [];
    $admin_pages = [
        // Examples:
        // path              => name,
        // path              => [name, permission],

        'admin_arrive'           => [admin_arrive_title(), 'users.arrive.list'],
        'admin_active'           => ['Active Critters', 'admin_active'],
        'users'                  => ['All Critters', 'admin_user'],
        'admin_free'             => ['Free Critters','admin_free'],
        'admin/questions'        => ['Answer questions', 'question.edit'],
        'admin/shifttypes'       => ['shifttype.shifttypes', 'shifttypes.view'],
        'admin/certifications'   => ['Certifications', 'certificates.manage'],
        'admin_shifts'           => ['Create shifts', 'admin_shifts'],
        'admin/locations'        => ['location.locations', 'admin_locations'],
        'admin_groups'           => ['Grouprights', 'admin_groups'],
        'admin/schedule'         => ['schedule.import', 'schedule.import'],
        'admin/logs'             => ['log.log', 'admin_log'],
        'admin/purge'            => ['Purge Data', 'user.type.admin'],
        'admin/config'           => ['config.config', 'config.edit'],
        'adminv2/export'         => ['V2-Export', 'admin_user'],
    ];

    if (config('autoarrive')) {
        unset($admin_pages['admin_arrive']);
    }

    foreach ($admin_pages as $menu_page => $options) {
        if (!menu_is_allowed(permissions: $options)) {
            continue;
        }

        $title = ((array) $options)[0];
        $admin_menu[] = toolbar_dropdown_item(
            url(str_replace('_', '-', $menu_page)),
            htmlspecialchars(__($title)),
            $menu_page == $page || Str::startsWith($page, $menu_page . '/')
        );
    }

    if (count($admin_menu) > 0) {
        $menu[] = toolbar_dropdown(__('Admin'), $admin_menu);
    }

    return join("\n", $menu);
}

/**
 * If permission is not set, it will be visible
 * Removed the feature the use the page name as permission setting
 *
 * @param string|string[] $permissions
 *
 * @return bool
 */
function menu_is_allowed($permissions)
{
    $permissions = (array) $permissions;

    if (isset($permissions[1])) {
        return auth()->can(abilities: $permissions[1]);
    } else {
        // If the permission is not set, allow the creation
        return true;
    }
}

/**
 * Adds location navigation to the given menu.
 *
 * @param string[] $menu Rendered menu
 * @return string[]
 */
function make_location_navigation(array $menu): array
{
    if (!auth()->can('view_locations')) {
        return $menu;
    }

    // Get a list of all locations
    $query = Location::query();

    if (!auth()->can('user.type.staff')) {
        $query->whereNot('staff_only', true);
    }
    $locations = $query->orderBy('name')->get();

    $location_menu = [];
    if (auth()->can('admin_locations')) {
        $location_menu[] = toolbar_dropdown_item(
            url('/admin/locations'),
            __('Manage locations'),
            false,
            'list'
        );
    }
    if (count($location_menu) > 0) {
        $location_menu[] = toolbar_dropdown_item_divider();
    }
    foreach ($locations as $location) {
        $location_menu[] = toolbar_dropdown_item(location_link($location), $location->name, false, 'pin-map-fill');
    }
    if (count($location_menu) > 0) {
        $menu[] = toolbar_dropdown(__('Locations'), $location_menu);
    }
    return $menu;
}

/**
 * Renders language selection.
 *
 * @return array
 */
function make_language_select()
{
    $request = app('request');
    $activeLocale = session()->get('locale');

    $items = [];
    foreach (config('locales') as $locale => $name) {
        $url = url($request->getPathInfo(), [...$request->getQueryParams(), 'set-locale' => $locale]);

        $items[] = toolbar_dropdown_item(
            htmlspecialchars($url),
            $name,
            $locale == $activeLocale
        );
    }
    return $items;
}

/**
 * Renders a hint for new questions to answer.
 *
 * @return string|null
 */
function admin_new_questions()
{
    if (!auth()->can('question.edit') || current_page() == 'admin/questions') {
        return null;
    }

    $unanswered_questions = Question::unanswered()->count();
    if (!$unanswered_questions) {
        return null;
    }

    return '<a href="' . url('/admin/questions') . '">'
        . __('There are unanswered questions!')
        . '</a>';
}
