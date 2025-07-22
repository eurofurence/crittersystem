---
title: Docker Deploy
---

## Docker

### Image

```
docker pull ghcr.io/eurofurence/crittersystem:latest
```

### Docker Compose

Template:

```yaml
services:
  # ------------------------------------------------------------------------------
  critter_server:
    image: ghcr.io/eurofurence/crittersystem:latest
    restart: unless-stopped

    environment:
      MYSQL_HOST: critter_database
      MYSQL_USER: critter_user
      MYSQL_PASSWORD: critter_password
      MYSQL_DATABASE: critter_db

    ports:
      - '5080:80'

    networks:
      - database
      - internet

    depends_on:
      - critter_database

  # ------------------------------------------------------------------------------
  critter_database:
    image: mariadb:10.2
    restart: unless-stopped

    environment:
      MYSQL_DATABASE: critter_db
      MYSQL_USER: critter_user
      MYSQL_PASSWORD: critter_password
      MYSQL_RANDOM_ROOT_PASSWORD: 1
      MYSQL_INITDB_SKIP_TZINFO: 'yes'

    volumes:
      - db:/var/lib/mysql

    networks:
      - database

# ------------------------------------------------------------------------------
volumes:
  db: {}

networks:
  database:
    internal: true
  internet:
```

### Variables

| Variables                   | Docker ENV                  | Default Value                                    |
| --------------------------- | --------------------------- | ------------------------------------------------ |
| 'host'                      | 'MYSQL_HOST'                | _localhost_                                      |
| 'database'                  | 'MYSQL_DATABASE'            | _critterdb_                                      |
| 'username'                  | 'MYSQL_USER'                | _root_                                           |
| 'password'                  | 'MYSQL_PASSWORD'            | `empty`                                          |
| 'api_key'                   | 'API_KEY'                   | `empty`                                          |
| 'maintenance'               | 'MAINTENANCE'               | false                                            |
| 'app_name'                  | 'APP_NAME'                  | Critter System                                   |
| 'environment'               | 'ENVIRONMENT'               | production                                       |
| 'url'                       | 'APP_URL'                   | `empty`                                          |
| 'faq.faq'                   | 'FAQ_URL'                   | `/faq`                                           |
| 'Contact'                   | 'CONTACT_EMAIL'             | mailto:critter@eurofurence.org                   |
| 'general.email'             | 'CONTACT_EMAIL'             | mailto:critter@eurofurence.org                   |
| 'faq_text'                  | 'FAQ_TEXT'                  |                                                  |
| 'documentation_url'         | 'DOCUMENTATION_URL'         | 'https://github.com/eurofurence/crittersystem/'  |
| 'driver'                    | 'MAIL_DRIVER'               | mail                                             |
| 'address'                   | 'MAIL_FROM_ADDRESS'         | noreply@example.com                              |
| 'name'                      | 'MAIL_FROM_NAME'            | env 'APP_NAME'                                   |
| 'host'                      | 'MAIL_HOST'                 | 'localhost'                                      |
| 'port'                      | 'MAIL_PORT'                 | 587                                              |
| 'tls'                       | 'MAIL_TLS'                  |                                                  |
| 'username'                  | 'MAIL_USERNAME'             |                                                  |
| 'password'                  | 'MAIL_PASSWORD'             |                                                  |
| 'sendmail'                  | 'MAIL_SENDMAIL'             | '/usr/sbin/sendmail -bs'                         |
| 'privacy_email'             | 'PRIVACY_EMAIL'             |                                                  |
| 'enable_email_goodie'       | 'ENABLE_EMAIL_GOODIE'       | false                                            |
| 'setup_admin_password'      | 'SETUP_ADMIN_PASSWORD'      |                                                  |
| 'theme'                     | 'THEME'                     | 21                                               |
| 'home_site'                 | 'HOME_SITE'                 | 'news'                                           |
| 'display_news'              | 'DISPLAY_NEWS'              | 10                                               |
| 'registration_enabled'      | 'REGISTRATION_ENABLED'      | true                                             |
| 'external_registration_url' | 'EXTERNAL_REGISTRATION_URL' |                                                  |
| 'pronoun'                   | 'PRONOUN_REQUIRED'          | false                                            |
| 'firstname'                 | 'FIRSTNAME_REQUIRED'        | false                                            |
| 'lastname'                  | 'LASTNAME_REQUIRED'         | false                                            |
| 'tshirt_size'               | 'TSHIRT_SIZE_REQUIRED'      | true                                             |
| 'mobile'                    | 'MOBILE_REQUIRED'           | false                                            |
| 'dect'                      | 'DECT_REQUIRED'             | false                                            |
| 'signup_requires_arrival'   | 'SIGNUP_REQUIRES_ARRIVAL'   | false                                            |
| 'autoarrive'                | 'AUTOARRIVE'                | false                                            |
| 'supporters_can_promote'    | 'SUPPORTERS_CAN_PROMOTE'    | false                                            |
| 'signup_advance_hours'      | 'SIGNUP_ADVANCE_HOURS'      | 0                                                |
| 'signup_post_minutes'       | 'SIGNUP_POST_MINUTES'       | 0                                                |
| 'signup_post_fraction'      | 'SIGNUP_POST_FRACTION'      | 0                                                |
| 'last_unsubscribe'          | 'LAST_UNSUBSCRIBE'          | 3                                                |
| 'password_algorithm'        | 'PASSWORD_ALGORITHM'        | PASSWORD_DEFAULT                                 |
| 'password_min_length'       | 'PASSWORD_MIN_LENGTH'       | 8                                                |
| 'enable_password'           | 'ENABLE_PASSWORD'           | true                                             |
| 'enable_dect'               | 'ENABLE_DECT'               | true                                             |
| 'enable_mobile_show'        | 'ENABLE_MOBILE_SHOW'        | false                                            |
| 'username_regex'            | 'USERNAME_REGEX'            | '/([^\p{L}\p{N}_.-]+)/ui'                        |
| 'enable_full_name'          | 'ENABLE_FULL_NAME'          | false                                            |
| 'display_full_name'         | 'DISPLAY_FULL_NAME'         | false                                            |
| 'ENABLE_FULL_NAME'          | false                       |                                                  |
| 'enable_pronoun'            | 'ENABLE_PRONOUN'            | true                                             |
| 'enable_planned_arrival'    | 'ENABLE_PLANNED_ARRIVAL'    | true                                             |
| 'enable_force_active'       | 'ENABLE_FORCE_ACTIVE'       | true                                             |
| 'enable_self_worklog'       | 'ENABLE_SELF_WORKLOG'       | true                                             |
| 'goodie_type'               | 'GOODIE_TYPE'               | 'goodie'                                         |
| 'enable_voucher'            | 'ENABLE_VOUCHER'            | true                                             |
| 'max_freeloadable_shifts'   | 'MAX_FREELOADABLE_SHIFTS'   | 2                                                |
| 'timezone'                  | 'TIMEZONE'                  | 'Europe/Berlin'                                  |
| 'enabled'                   | 'NIGHT_SHIFTS'              | true                                             |
| 'start'                     | 'NIGHT_SHIFTS_START'        | 2                                                |
| 'end'                       | 'NIGHT_SHIFTS_END'          | 8                                                |
| 'multiplier'                | 'NIGHT_SHIFTS_MULTIPLIER'   | 2                                                |
| 'initial_vouchers'          | 'INITIAL_VOUCHERS'          | 0                                                |
| 'shifts_per_voucher'        | 'SHIFTS_PER_VOUCHER'        | 0                                                |
| 'hours_per_voucher'         | 'HOURS_PER_VOUCHER'         | 2                                                |
| 'voucher_start'             | 'VOUCHER_START' ?: null,    |                                                  |
| 'driving_license_enabled'   | 'DRIVING_LICENSE_ENABLED'   | true                                             |
| 'ifsg_enabled'              | 'IFSG_ENABLED'              | false                                            |
| 'ifsg_light_enabled'        | 'IFSG_LIGHT_ENABLED'        | false                                            |
| 'default_locale'            | 'DEFAULT_LOCALE'            | 'en_US'                                          |
| 'tshirt_link'               | 'TSHIRT_LINK'               |                                                  |
| 'enable_day_of_event'       | 'ENABLE_DAY_OF_EVENT'       | false                                            |
| 'event_has_day0'            | 'EVENT_HAS_DAY0'            | true                                             |
| 'filter_max_duration'       | 'FILTER_MAX_DURATION'       | 0                                                |
| 'driver'                    | 'SESSION_DRIVER'            | 'pdo'                                            |
| 'name'                      | 'SESSION_NAME'              | 'session'                                        |
| 'lifetime'                  | 'SESSION_LIFETIME'          | 30                                               |
| 'trusted_proxies'           | 'TRUSTED_PROXIES'           | ['127.0.0.0/8', '::ffff:127.0.0.0/8', '::1/128'] |
| 'add_headers'               | 'ADD_HEADERS'               | true                                             |

---
