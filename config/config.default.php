<?php

declare(strict_types=1);

// #####################################################################
// To change or overwrite some settings, create a config.php
// Keep only the settings you want to change inside the config.php
// #####################################################################

return [
    // #################################################################
    // Group: Main Application
    // #################################################################
    // Application name (not the event name)
    'app_name'                => env('APP_NAME', 'Critter System'), //NEW ENV NAME: APP_NAME

    // Set to development to enable debugging messages [production, development]
    'environment'             => env('ENVIRONMENT', 'production'), //NEW ENV NAME: APP_ENV

    // Application URL and base path to use instead of the auto-detected
    'url'                     => env('APP_URL'), //NEW ENV NAME: APP_URL

    // Enable maintenance mode (show a static page to all users)
    'maintenance'             => (bool) env('MAINTENANCE', false), //NEW ENV NAME: APP_ENABLE_MAINTENANCE

    // For accessing /metrics (and /stats)
    'api_key'                 => env('API_KEY', ''), //NEW ENV NAME: APP_METRICS_API_KEY

    // Login DEV Warning Message
    'login_dev_warning'     => env('DEV_WARNING_MESSAGE', false), //NEW ENV NAME: APP_ENABLE_DEMO_MODE

    // Initial admin password, configured on first migration
    'setup_admin_password'    => env('SETUP_ADMIN_PASSWORD'), //NEW ENV NAME: APP_INITIAL_ADMIN_PASSWORD

    // Redirect to this site after logging in or when clicking the page name
    // Must be one of news, meetings, user_shifts, angeltypes, questions
    'home_site'               => env('HOME_SITE', 'news'),

    // Required user fields
    'required_user_fields' => [
        'pronoun'            => (bool) env('PRONOUN_REQUIRED', false),
        'firstname'          => (bool) env('FIRSTNAME_REQUIRED', false),
        'lastname'           => (bool) env('LASTNAME_REQUIRED', false),
        'tshirt_size'        => (bool) env('TSHIRT_SIZE_REQUIRED', false), //TODO: REMOVE
        'mobile'             => (bool) env('MOBILE_REQUIRED', false),
        'dect'               => (bool) env('DECT_REQUIRED', false),
    ],

    // Local time zone
    'timezone'                => env('TIMEZONE', 'Europe/Berlin'),

    // The default locale to use
    'default_locale'          => env('DEFAULT_LOCALE', 'en_US'),

    // Available locales in /resources/lang/
    // To disable a locale in config.php, you can set its value to null
    'locales'                 => [
        'de_DE' => 'Deutsch',
        'en_US' => 'English',
    ],

    // Default theme - ID comes from THEMES section
    'theme'                   => env('THEME', 21),
    // #################################################################

    // #################################################################
    // Group: DATABASE
    // #################################################################
    // MySQL-Connection Settings
    'database'                => [
        'host'     => env('MYSQL_HOST', 'localhost'),
        'database' => env('MYSQL_DATABASE', 'critterdb'),
        'username' => env('MYSQL_USER', ''),
        'password' => env('MYSQL_PASSWORD', ''),
    ],
    // #################################################################

    // #################################################################
    // Group: E-mail
    // #################################################################
    'email'                   => [
        // Can be mail, smtp, sendmail, log or an symfony mailer dsn string like smtps://[usr]:[pass]@smtp.foo.bar:465
        'driver' => env('MAIL_DRIVER', 'mail'),
        'from'   => [
            // From address of all emails
            'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'name'    => env('MAIL_FROM_NAME', env('APP_NAME', 'Critter System')),
        ],

        'host'       => env('MAIL_HOST', 'localhost'),
        'port'       => env('MAIL_PORT', 587),
        // If tls transport encryption should be enabled
        'tls'        => env('MAIL_TLS'),
        'username'   => env('MAIL_USERNAME'),
        'password'   => env('MAIL_PASSWORD'),
        'sendmail'   => env('MAIL_SENDMAIL', '/usr/sbin/sendmail -bs'),
    ],
    // #################################################################

    // #################################################################
    // Group: Website Features / Customizations
    // #################################################################

    // Show opt-in on user profile and registration pages to save some personal data after the event (???)
    'enable_email_goodie' => (bool) env('ENABLE_EMAIL_GOODIE', false),

    // TODO: REMOVE
    // Enable Driving License
    'driving_license_enabled' => (bool) env('DRIVING_LICENSE_ENABLED', false),

    // Header links
    // Available link placeholders: %lang%
    // To disable a header_item in config.php, you can set its value to null
    'header_items'            => [
        // Name can be a translation string, permission is an engelsystem privilege
        // 'Name' => 'URL',
        // 'some.key' => ['URL', 'permission'],

        //'Foo' => ['https://foo.bar/batz-%lang%.html', 'logout'], // Permission: for logged-in users
    ],

    // Footer links
    // To disable a footer item in config.php, you can set its value to null
    'footer_items'            => [
        // Name can be a translation string, permission is a engelsystem privilege
        // 'Name' => 'URL',
        // 'some.key' => ['URL', 'permission'],

        // URL to faq page
        'faq.faq' => [env('FAQ_URL', '/faq'), 'faq.view'],

        // Contact email address, linked on every page
        'Contact' => env('CONTACT_EMAIL', 'mailto:noreply@example.com'),
    ],

    // Other ways to ask the heaven
    // Multiple contact options / links are possible, analogue to footer_items
    'contact_options' => [
        // E-mail address
        'general.email' => env('CONTACT_EMAIL', 'mailto:noreply@example.com'),
    ],

    // TODO: MOVE THIS TO DB ENTRY
    // Additional text displayed on the FAQ page, rendered as markdown
    'faq_text'                => env('FAQ_TEXT'),

    // Link to documentation/help
    'documentation_url'       => env('DOCUMENTATION_URL', 'https://github.com/eurofurence/crittersystem/'),

    // Your privacy@ contact address
    'privacy_email' => env('PRIVACY_EMAIL'),

    // Number of News shown on one site and for feed readers (minimum 1)
    'display_news'            => env('DISPLAY_NEWS', 10),

    // A list of credits
    'credits'                 => [
        'Contribution' => 'Please visit `
            . `[eurofurence/crittersystem GitHub](https://github.com/eurofurence/crittersystem) '
            . 'if you want to contribute, have found any [bugs](https://github.com/eurofurence/crittersystem/issues) '
            . 'or need help.',
    ],

    // #################################################################
    // User Information / Registration / Password
    // #################################################################

    // Users are able to Register
    'registration_enabled'    => (bool) env('REGISTRATION_ENABLED', true),

    // URL to external registration page, linked from login page
    'external_registration_url'   => env('EXTERNAL_REGISTRATION_URL'),

    // Enable the planned arrival/leave date
    'enable_planned_arrival'  => (bool) env('ENABLE_PLANNED_ARRIVAL', true),

    // Whether force active should be enabled
    'enable_force_active' => (bool) env('ENABLE_FORCE_ACTIVE', true),

    // Allow users with sufficient permission to add worklogs for themselves
    'enable_self_worklog' => (bool) env('ENABLE_SELF_WORKLOG', true),

    // Enable displaying the pronoun fields
    'enable_pronoun'          => (bool) env('ENABLE_PRONOUN', true),

    // Whether the DECT field should be enabled
    'enable_dect'             => (bool) env('ENABLE_DECT', true),

    // Whether the mobile number will be shown to other users
    'enable_mobile_show'      => (bool) env('ENABLE_MOBILE_SHOW', false),

    // show users registration number retrieved from the registration service
    'display_badge_number'   => env('DISPLAY_BADGE_NUMBER', false),

    // TODO: REMOVE
    // Instruction in accordance with § 43 Para. 1 of the German Infection Protection Act (IfSG)
    'ifsg_enabled'           => (bool) env('IFSG_ENABLED', false),

    // TODO: REMOVE
    // Instruction only onsite in accordance with § 43 Para. 1 of the German Infection Protection Act (IfSG)
    'ifsg_light_enabled'           => env('IFSG_LIGHT_ENABLED', false) && env('IFSG_ENABLED', false),

    // Whether to show the current day of the event (-2, -1, 0, 1, 2…) in footer and on the dashboard.
    // The event start date has to be set for it to appear.
    'enable_day_of_event' => (bool) env('ENABLE_DAY_OF_EVENT', true),

    // If true there will be a day 0 (-1, 0, 1…). If false there won't (-1, 1…)
    'event_has_day0' => (bool) env('EVENT_HAS_DAY0', true),

    // Regular expression describing a FALSE username.
    // Per default usernames must only contain alphanumeric chars, "-", "_" or ".".
    'username_regex' => (string) env('USERNAME_REGEX', '/([^\p{L}\p{N}_.-]+)/ui'),

    // Enable first name and last name
    'enable_full_name'        => (bool) env('ENABLE_FULL_NAME', false),

    // Show a users first name and last name instead of username
    'display_full_name'  => env('DISPLAY_FULL_NAME', false)
        && env('ENABLE_FULL_NAME', false),

    // Whether the login and registration via password should be enabled (login will be hidden if false)
    // This is useful when using oauth, disabling it also disables normal registration without oauth
    // HOWEVER, if you have no oAuth enabled - this parameter is ignored otherwise you are locked out
    'enable_password'         => (bool) env('ENABLE_PASSWORD', true),

    // Define the algorithm to use for `password_verify()`
    // If a user password is hashed with an old algorithm, the password will be converted to the new format on login
    // See https://secure.php.net/manual/en/password.constants.php for a complete list
    'password_algorithm'      => env('PASSWORD_ALGORITHM', PASSWORD_DEFAULT),

    // The minimum length for passwords
    'password_min_length'     => env('PASSWORD_MIN_LENGTH', 8),

    // Whether newly-registered users should automatically be marked as arrived
    'autoarrive'              => (bool) env('AUTOARRIVE', false),

    // Supporters of a team (angeltype) can promote other users of the team (angeltype) to supporter
    'supporters_can_promote' => (bool) env('SUPPORTERS_CAN_PROMOTE', false),

    // Hide columns in backend user view. Possible values are any sortable parameters of the table.
    'disabled_user_view_columns' => [],
    // #################################################################

    // #################################################################
    // Group: OAUTH
    // #################################################################
    'oauth'                   => [
        // '[name]' => [config]
        'ef' => [
            // Enable Oauth
            'enabled' => env(key: 'APP_OAUTH_EF_ENABLED', default: true),

            // Name shown to the user
            'name' => env(key: 'APP_OAUTH_EF_NAME', default: 'Eurofurence IDP'),

            // Auth client ID
            // 'client_id' => env(key: 'APP_OAUTH_EF_CLIENT_ID', default: null),
            'client_id' => env(key: 'APP_OAUTH_EF_CLIENT_ID', default: 'critter-dev-test'),

            // Auth client secret
            // 'client_secret' => env(key: 'APP_OAUTH_EF_CLIENT_SECRET', default: null),
            'client_secret' => env(key: 'APP_OAUTH_EF_CLIENT_SECRET', default: 'ByuUosSphby4g2ZYlg0WSqkGX2my5837'),

            // Authentication URL
            // 'url_auth' => env(key: 'APP_OAUTH_GENERIC_URL_AUTH', default: null),
            'url_auth' => 'https://sso.rustybraze.net/realms/ef-devops/protocol/openid-connect/auth',

            // Token URL
            // 'url_token' => env(key: 'APP_OAUTH_GENERIC_URL_TOKEN', default: null),
            'url_token' => 'https://sso.rustybraze.net/realms/ef-devops/protocol/openid-connect/token',

            // User info URL which provides userdata
            // 'url_info' => env(key: 'APP_OAUTH_GENERIC_URL_USER_INFO', default: null),
            'url_info' => 'https://sso.rustybraze.net/realms/ef-devops/protocol/openid-connect/userinfo',

            // User URL to provider, linked on provider settings page (optional)
            // 'url' => env(key: 'APP_OAUTH_GENERIC_URL_PROVIDER', default: null),
            'url' => 'https://sso.rustybraze.net/realms/ef-devops',

            // OAuth Scopes
            'scope' => env(key: 'APP_OAUTH_EF_SCOPE', default: ['openid', 'profile', 'email', 'groups']),

            // Info unique user id field
            'id' => env(key: 'APP_OAUTH_EF_MAP_USER_ID', default: 'sub'),

            // ----------------------------------------------------------------
            // The following fields are used for registration
            // ----------------------------------------------------------------
            // Info username field
            'username' => env(key: 'APP_OAUTH_EF_MAP_USERNAME', default: 'name'),

            // Info email field (optional)
            'email' => env(key: 'APP_OAUTH_EF_MAP_EMAIL', default: 'email'),

            // Groups
            'groups' => env(key: 'APP_OAUTH_EF_MAP_GROUPS', default: 'groups'),

            // Info first name field (optional)
            'first_name' => env(key: 'APP_OAUTH_EF_MAP_FIRST_NAME', default: 'first-name'),

            // Info last name field (optional)
            'last_name' => env(key: 'APP_OAUTH_EF_MAP_LAST_NAME', default: 'last-name'),

            // Whether info attributes are nested arrays (optional)
            // For example {"user":{"name":"foo"}} can be accessed using user.name
            'nested_info' => env(key: 'APP_OAUTH_EF_NESTED_ARRAYS_ENABLE', default: false),

            // Only show after clicking the page title (optional)
            'hidden' => env(key: 'APP_OAUTH_EF_HIDDEN', default: false),

            // Mark user as arrived when using this provider (optional)
            'mark_arrived' => env(key: 'APP_OAUTH_EF_POLICY_MARK_ARRIVIED', default: false),

            // If the password field should be enabled on registration (optional)
            'enable_password' => env(key: 'APP_OAUTH_EF_POLICY_ENABLE_PASSWORD', default: false),

            // Allow registration even if disabled in config (optional)
            'allow_registration' => env(key: 'APP_OAUTH_EF_POLICY_ALLOW_REGISTRATION', default: null),

            // Registration API to fetch reg num for user
            'badge_number_api' => env(key: 'APP_OAUTH_EF_URL_BADGE_API', default: null),
        ],
        'generic' => [
            // Enable Oauth
            'enabled' => env(key: 'APP_OAUTH_GENERIC_ENABLED', default: false),

            // Name shown to the user
            'name' => env(key: 'APP_OAUTH_GENERIC_NAME', default: 'Generic SSO'),

            // Auth client ID
            'client_id' => env(key: 'APP_OAUTH_GENERIC_CLIENT_ID', default: null),

            // Auth client secret
            'client_secret' => env(key: 'APP_OAUTH_GENERIC_CLIENT_SECRET', default: null),

            // Authentication URL
            'url_auth' => env(key: 'APP_OAUTH_GENERIC_URL_AUTH', default: null),

            // Token URL
            'url_token' => env(key: 'APP_OAUTH_GENERIC_URL_TOKEN', default: null),

            // User info URL which provides userdata
            'url_info' => env(key: 'APP_OAUTH_GENERIC_URL_USER_INFO', default: null),

            // User URL to provider, linked on provider settings page (optional)
            'url' => env(key: 'APP_OAUTH_GENERIC_URL_PROVIDER', default: null),

            // OAuth Scopes
            'scope' => env(key: 'APP_OAUTH_GENERIC_SCOPE', default: ['openid']),

            // Info unique user id field
            'id' => env(key: 'APP_OAUTH_GENERIC_MAP_USER_ID', default: 'uuid'),

            // ----------------------------------------------------------------
            // The following fields are used for registration
            // ----------------------------------------------------------------
            // Info username field (optional)
            'username' => env(key: 'APP_OAUTH_GENERIC_MAP_USERNAME', default: 'name'),

            // Info email field (optional)
            'email' => env(key: 'APP_OAUTH_GENERIC_MAP_EMAIL', default: 'email'),

            // Auto join teams
            // Info groups field (optional)
            'groups' => env(key: 'APP_OAUTH_GENERIC_MAP_GROUPS', default: 'groups'),

            // Info first name field (optional)
            'first_name' => env(key: 'APP_OAUTH_GENERIC_MAP_FIRST_NAME', default: 'first-name'),

            // Info last name field (optional)
            'last_name' => env(key: 'APP_OAUTH_GENERIC_MAP_LAST_NAME', default: 'last-name'),

            // Whether info attributes are nested arrays (optional)
            // For example {"user":{"name":"foo"}} can be accessed using user.name
            'nested_info' => env(key: 'APP_OAUTH_GENERIC_NESTED_ARRAYS_ENABLE', default: false),

            // Only show after clicking the page title (optional)
            'hidden' => env(key: 'APP_OAUTH_GENERIC_HIDDEN', default: false),

            // Mark user as arrived when using this provider (optional)
            'mark_arrived' => env(key: 'APP_OAUTH_GENERIC_POLICY_MARK_ARRIVIED', default: false),

            // If the password field should be enabled on registration (optional)
            'enable_password' => env(key: 'APP_OAUTH_GENERIC_POLICY_ENABLE_PASSWORD', default: false),

            // Allow registration even if disabled in config (optional)
            'allow_registration' => env(key: 'APP_OAUTH_GENERIC_POLICY_ALLOW_REGISTRATION', default: null),

            // Registration API to fetch reg num for user
            'badge_number_api' => env(key: 'APP_OAUTH_GENERIC_URL_BADGE_API', default: null),
        ],
    ],
    // #################################################################

    // #################################################################
    // Group: Themes
    // #################################################################

    // Supported themes
    // To disable a theme in config.php, you can set its value to null
    'themes' => [
        21 => [
            'name' => 'Eurofurence 2025',
            'type' => 'dark',
            'navbar_classes' => 'navbar-dark',
        ],
        20 => [
            'name' => 'Eurofurence 2024 - Cyberpunk',
            'type' => 'dark',
            'navbar_classes' => 'navbar-dark',
        ],
        19 => [
            'name' => 'Eurofurence Light',
            'type' => 'light',
            'navbar_classes' => 'navbar-light bg-light',
        ],
        18 => [
            'name' => 'Eurofurence Dark',
            'type' => 'dark',
            'navbar_classes' => 'navbar-primary navbar-dark bg-black border-dark',
        ],
//        17 => [
//            'name' => 'Engelsystem 37c3 (2023)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark',
//        ],
//        16 => [
//            'name' => 'Engelsystem cccamp23 (2023)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark',
//        ],
//        15 => [
//            'name' => 'Engelsystem rC3 (2021)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark',
//        ],
//        14 => [
//            'name' => 'Engelsystem rC3 teal (2020)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        13 => [
//            'name' => 'Engelsystem rC3 violet (2020)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        12 => [
//            'name' => 'Engelsystem 36c3 (2019)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        10 => [
//            'name' => 'Engelsystem cccamp19 green (2019)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        9 => [
//            'name' => 'Engelsystem cccamp19 yellow (2019)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        8 => [
//            'name' => 'Engelsystem cccamp19 blue (2019)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        7 => [
//            'name' => 'Engelsystem 35c3 dark (2018)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-primary navbar-dark bg-black border-primary',
//        ],
//        6 => [
//            'name' => 'Engelsystem 34c3 dark (2017)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        5 => [
//            'name' => 'Engelsystem 34c3 light (2017)',
//            'type' => 'light',
//            'navbar_classes' => 'navbar-light bg-light',
//        ],
//        4 => [
//            'name' => 'Engelsystem 33c3 (2016)',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-body border-dark',
//        ],
//        3 => [
//            'name' => 'Engelsystem 32c3 (2015)',
//            'type' => 'light',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        2 => [
//            'name' => 'Engelsystem cccamp15',
//            'type' => 'light',
//            'navbar_classes' => 'navbar-light bg-light',
//        ],
//        11 => [
//            'name' => 'Engelsystem high contrast',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        1 => [
//            'name' => 'Engelsystem dark',
//            'type' => 'dark',
//            'navbar_classes' => 'navbar-dark bg-black border-dark',
//        ],
//        0 => [
//            'name' => 'Engelsystem light',
//            'type' => 'light',
//            'navbar_classes' => 'navbar-light bg-light',
//        ],
    ],
    // #################################################################

    // #################################################################
    // Shift settings / Policies
    // #################################################################
    // Only arrived users can sign up for shifts
    'signup_requires_arrival' => (bool) env('SIGNUP_REQUIRES_ARRIVAL', true),

    // Only allow shift signup this number of hours in advance
    // Setting this to 0 disables the feature
    'signup_advance_hours'    => env('SIGNUP_ADVANCE_HOURS', 0),

    // Allow signup this many minutes after the start of the shift.
    // If signup_post_fraction is set, it's first applied before adding the number of minutes specified here.
    'signup_post_minutes'     => env('SIGNUP_POST_MINUTES', 15),

    // Allow signup this fraction of the shift length after the start of the shift.
    // Example: If it is set to 1, signup is allowed until the end of a shift
    //          If it is set to 0.5, signup is allowed for the first half of a shift
    // If signup_post_minutes is set, this is first applied and then the signup_post_minutes added on top.
    'signup_post_fraction'    => env('SIGNUP_POST_FRACTION', 0),

    // Number of hours that a user can sign out of own shifts beforehand
    'last_unsubscribe'        => env('LAST_UNSUBSCRIBE', 1),

    // #################################################################
    // Goodies / TShirts / ETC
    // #################################################################

    // TODO: REMOVE
    // Resembles the Goodie Type. There are three options:
    // 'none' => no goodie at all
    // 'goodie' => a goodie which has no sizing options
    // 'tshirt' => goodie that is called tshirt and has sizing options
    'goodie_type'             => env('GOODIE_TYPE', 'goodie'),

    // TODO: REMOVE
    // Enable vouchers
    'enable_voucher'          => (bool) env('ENABLE_VOUCHER', true),

    // TODO: REMOVE
    // Voucher calculation
    'voucher_settings'        => [
        'initial_vouchers'   => env('INITIAL_VOUCHERS', 0),
        'shifts_per_voucher' => env('SHIFTS_PER_VOUCHER', 0),
        'hours_per_voucher'  => env('HOURS_PER_VOUCHER', 2),
        // 'Y-m-d' formatted
        'voucher_start'      => env('VOUCHER_START') ?: null,
    ],

    // TODO: REMOVE
    // Available T-Shirt sizes
    // To disable a t-shirt size in config.php, you can set its value to null
    'tshirt_sizes'            => [
        'S'    => 'Small Straight-Cut',
        'S-F'  => 'Small Fitted-Cut',
        'M'    => 'Medium Straight-Cut',
        'M-F'  => 'Medium Fitted-Cut',
        'L'    => 'Large Straight-Cut',
        'L-F'  => 'Large Fitted-Cut',
        'XL'   => 'XLarge Straight-Cut',
        'XL-F' => 'XLarge Fitted-Cut',
        '2XL'  => '2XLarge Straight-Cut',
        '3XL'  => '3XLarge Straight-Cut',
        '4XL'  => '4XLarge Straight-Cut',
    ],

    // TODO: REMOVE
    // T-shirt Size-Guide link
    'tshirt_link' => env('TSHIRT_LINK'),


    // #################################################################
    // Group: Shift and event configuration
    // #################################################################

    // Multiply 'night shifts' and freeloaded shifts (start or end between 2 and 8 exclusive) by 2 in goodie score
    // Goodies must be enabled to use this feature
    'night_shifts'            => [
        'enabled'    => (bool) env('NIGHT_SHIFTS', true), // Disable to weigh every shift the same
        'start'      => env('NIGHT_SHIFTS_START', 2), // Starting from hour
        'end'        => env('NIGHT_SHIFTS_END', 8), // Ends at (without including) hour
        'multiplier' => env('NIGHT_SHIFTS_MULTIPLIER', 2),
    ],

    'metrics'                 => [
        // User work buckets in seconds
        'work'    => [1 * 60 * 60, 1.5 * 60 * 60, 2 * 60 * 60, 3 * 60 * 60, 5 * 60 * 60, 10 * 60 * 60, 20 * 60 * 60],
        'voucher' => [0, 1, 2, 3, 5, 10, 15, 20],
    ],

    // Shifts overview
    // Set max number of hours that can be shown at once
    // 0 means no limit
    'filter_max_duration'     => env('FILTER_MAX_DURATION', 0),

    // Number of shifts to freeload until a user is locked from shift signup.
    'max_freeloadable_shifts' => env('MAX_FREELOADABLE_SHIFTS', 2),
    // #################################################################

    // #################################################################
    // Group: Advanced - Header / Session / Proxy
    // #################################################################

    // Session config
    'session'                 => [
        // Supported: pdo or native
        'driver' => env('SESSION_DRIVER', 'pdo'),

        // Cookie name
        'name'   => env('SESSION_NAME', 'session'),

        // Lifetime in days
        'lifetime' => env('SESSION_LIFETIME', 30),
    ],

    // IP addresses of reverse proxies that are trusted, can be an array or a comma separated list
    'trusted_proxies'         => env('TRUSTED_PROXIES', ['127.0.0.0/8', '::ffff:127.0.0.0/8', '::1/128']),

    // Add additional headers
    'add_headers'             => (bool) env('ADD_HEADERS', true),
    // Predefined headers
    // To disable a header in config.php, you can set its value to null
    'headers'                 => [
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'sameorigin',
        'Referrer-Policy'         => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' =>
            'default-src \'self\'; '
            . ' frame-src https://nav.eurofurence.org; '
            . ' style-src \'self\' \'unsafe-inline\'; '
            . ' img-src \'self\' data:;',
        'X-XSS-Protection'        => '1; mode=block',
        'Feature-Policy'          => 'autoplay \'none\'',
        //'Strict-Transport-Security' => 'max-age=7776000',
        //'Expect-CT' => 'max-age=7776000,enforce,report-uri="[uri]"',
    ],


    // #################################################################
    // Policy features
    // #################################################################

    // TODO: Add External variables for easy config
    'policy'                => [
        // Adds the prefix to the visual text but do not affect the link
        'telegram_visual_prefix' => '',
        // Non-Staff - profile link redirects to Message system. False > Only shows the name (no link)
        'non_staff_message_shortcut' => true,
        // Non-Staff - Use Telegram for message. False > uses internal message system
        'non_staff_message_via_telegram' => true,
    ],

    // #################################################################
    // Group: DEVELOPMENT
    // #################################################################
    // You need to manually enable this and be sure to use the development image
    // var dump server
    'var_dump_server'         => [
        'host' => '127.0.0.1',
        'port' => '9912',
        'enable' => false,
    ],
];
