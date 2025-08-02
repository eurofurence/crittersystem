# Permission Matrix

## Global Overview

|    Category    |               Permission | Desc                                                   | Admin | API | Bureaucrat | Critter | Developer | Goodie Manager | Guest | Shift Coordinator | Staff - Internal | Voucher Critter | Welcome Critter |
| :------------: | -----------------------: | ------------------------------------------------------ | :---: | :-: | :--------: | :-----: | :-------: | :------------: | :---: | :---------------: | :--------------: | :-------------: | :-------------: |
|     Global     |                      api | Use the API                                            |  🟢   | 🟢  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Global     |                     atom | Atom news export                                       |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Global     |                     ical | iCal shift export                                      |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Global     |                    login | Logindialog                                            |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🟢   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Global     |                   logout | User darf sich ausloggen                               |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Global     |                    start | Startseite für Gäste/Nicht eingeloggte User            |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🟢   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Global     |                 register | Einen neuen Engel registerieren                        |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🟢   |        🟢         |        🔴        |       🔴        |       🔴        |
| Administrative |             admin_groups | Manage usergroups and their rights                     |  🟢   | 🔴  |     🔴     |   🔴    |    🟢     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
| Administrative |                admin_log | Display recent changes                                 |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
| Administrative |              config.edit | Edit the application configuration                     |  🟢   | 🔴  |     🔴     |   🔴    |    🟢     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
| Administrative |                 logs.all | View all logs                                          |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
| Administrative |          schedule.import | Import locations and shifts from schedule.xml          |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|    Location    |          admin_locations | Manage locations                                       |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|    Location    |           view_locations | User can view locations                                |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|    Meeting     |            user_meetings | Lists meetings (news)                                  |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🟢       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|    Message     |            user_messages | Writing and reading messages from user to user         |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|    Critter     |             admin_active | Mark angels as active and if they got a goodie.        |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|    Critter     |        admin_angel_types | Engel Typen administrieren                             |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|    Critter     |             admin_arrive | Mark angels when they arrive.                          |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|    Critter     |               admin_free | Show a list of free/unemployed angels.                 |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🟢        |
|    Critter     |               admin_user | Administrate the angels                                |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|    Critter     |    admin_user_angeltypes | Confirm restricted angel types                         |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|    Critter     |               angeltypes | View angeltypes                                        |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🟢        |
|    Critter     |          user_angeltypes | Join angeltypes.                                       |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|    Question    |             question.add | Ask questions                                          |  🟢   | 🔴  |     🟢     |   🟢    |    🔴     |       🟢       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|    Question    |            question.edit | Answer questions                                       |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
| Certification  |          user.drive.edit | Edit Driving License                                   |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
| Certification  |           user.ifsg.edit | Edit IfSG Certificate                                  |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|      FAQ       |                 faq.edit | Edit FAQ entries                                       |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|      FAQ       |                 faq.view | View FAQ entries                                       |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🟢       |  🟢   |        🔴         |        🔴        |       🔴        |       🔴        |
|      News      |               admin_news | Administrate the news section                          |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|      News      |                     news | Anzeigen der News-Seite                                |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🟢       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|      News      |            news_comments | User can comment news                                  |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|      News      |           news.highlight | Highlight News                                         |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🟢       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Shift      |             admin_shifts | Create shifts                                          |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🟢        |
|     Shift      |       admin_user_worklog | Manage user work log entries.                          |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|     Shift      |       shifts_json_export | Export shifts in JSON format                           |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Shift      |          shifttypes.edit | Edit shift types                                       |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|     Shift      |          shifttypes.view | View shift types                                       |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|     Shift      |            user_myshifts | Allow angels to view their own shifts and cancel them. |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Shift      |              user_shifts | Signup for shifts                                      |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|     Shift      |        user_shifts_admin | Signup other angels for shifts.                        |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🔴        |       🟢        |
|      User      |            user_settings | User profile settings                                  |  🟢   | 🔴  |     🔴     |   🟢    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|      User      |           user.info.edit | Edit User Info                                         |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🟢       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|      User      |           user.info.show | Show User Info                                         |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🔴       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |
|      User      |           user.nick.edit | Edit user nick                                         |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|      User      |             user.fa.edit | Edit User Force Active State                           |  🟢   | 🔴  |     🟢     |   🔴    |    🔴     |       🔴       |  🔴   |        🔴         |        🔴        |       🔴        |       🔴        |
|      User      |        users.arrive.list | View arrive angels list                                |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🟢        |       🟢        |
|     Staff      | user.type.internal_staff | Flag the user as Internal Staff                        |  🟢   | 🔴  |     🟢     |   🔴    |    🟢     |       🔴       |  🔴   |        🟢         |        🟢        |       🔴        |       🔴        |
|     Goodie     |             voucher.edit | Edit vouchers                                          |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🟢        |       🔴        |
|     Goodie     |         user.goodie.edit | Edit user goodies                                      |  🟢   | 🔴  |     🔴     |   🔴    |    🔴     |       🟢       |  🔴   |        🟢         |        🔴        |       🔴        |       🔴        |

## Departments

|  Category  | Permission  | Description                                    |
| :--------: | :---------: | ---------------------------------------------- |
| Department | dept.admin  | No restrictions                                |
| Department |  dept.add   | Add new Departments + allow view, edit, delete |
| Department |  dept.view  | View Departments                               |
| Department |  dept.edit  | Edit Departments                               |
| Department | dept.delete | Delete Departments - Dangerous permission      |

## EF 28 - Permissions

### Production System

Grouprights
Name Privileges
Angel angeltypes, atom, faq.view, ical, logout, news, news_comments, question.add, shifts_json_export, user_angeltypes, user_meetings, user_messages, user_myshifts, user_settings, user_shifts, view_locations
API api
Bureaucrat admin_angel_types, admin_locations, admin_log, api, logs.all, news.highlight, schedule.import, shifttypes.edit, user.fa.edit, user.info.edit, user.nick.edit, user.type.internal_staff
Developer admin_groups, config.edit, user.type.internal_staff
Goodie Manager admin_arrive, admin_news, admin_user, admin_user_worklog, faq.edit, faq.view, news, news.highlight, question.add, question.edit, user.goodie.edit, user.info.edit, user_meetings, user_shifts_admin, users.arrive.list, voucher.edit
Guest faq.view, login, register, start
Shift Coordinator admin_angel_types, admin_arrive, admin_free, admin_log, admin_news, admin_shifts, admin_user, admin_user_angeltypes, admin_user_worklog, faq.edit, question.edit, register, shifttypes.edit, shifttypes.view, user.drive.edit, user.goodie.edit, user.ifsg.edit, user.info.show, user.type.internal_staff, user_shifts_admin, users.arrive.list, voucher.edit
Staff - Internal user.type.internal_staff
Voucher Angel users.arrive.list, voucher.edit
Welcome Angel admin_free, admin_shifts, angeltypes, user_shifts_admin, users.arrive.list
