# PO File Guidelines

## Overview

This document provides guidelines for creating and updating `.po` translation files with a standardized hierarchical key structure. This ensures consistency and clarity across the project.

## PO File Structure

Each `.po` file contains key-value pairs where:

- **`msgid`**: The unique identifier for a translatable string.
- **`msgstr`**: The translated text.

Example:

```po
msgid "auth.password.error"
msgstr "Your password is incorrect. Please try again."
```

## Hierarchical Key Naming Convention

To maintain a structured and scalable format, keys should follow a hierarchical format:

```
<category>.<action>.<detail>
```

### Standard Categories

| Category       | Description                                |
| -------------- | ------------------------------------------ |
| `auth`         | Authentication and login-related messages  |
| `form`         | Form actions and interactions              |
| `ui`           | General user interface labels and elements |
| `notification` | System notifications and alerts            |
| `schedule`     | Shift scheduling and time management       |
| `misc`         | Miscellaneous or uncategorized terms       |

### Examples:

| msgid                     | Meaning                              |
| ------------------------- | ------------------------------------ |
| `auth.password.error`     | Error message for incorrect password |
| `form.submit`             | Label for form submission button     |
| `ui.general.add`          | UI label for adding an item          |
| `notification.email.sent` | Message indicating an email was sent |
| `schedule.shift.assigned` | Notification for assigned shift      |

## List of Existing Actions

To maintain consistency and prevent duplicate or inconsistent keys, developers should refer to the following list of existing actions before creating new ones:

| Action                      | Description                              |
| --------------------------- | ---------------------------------------- |
| `password.error`            | Error message for incorrect password     |
| `password.reset`            | Requesting a password reset              |
| `login.success`             | Message for successful login             |
| `login.failure`             | Message for failed login attempt         |
| `session.expired`           | Notification when the session expires    |
| `form.submit`               | Label for submitting a form              |
| `form.send_notification`    | Action to send notifications             |
| `general.add`               | UI action to add an item                 |
| `general.remove`            | UI action to remove an item              |
| `general.edit`              | UI action to edit an item                |
| `notification.email.sent`   | Notification when an email is sent       |
| `notification.push.sent`    | Notification when a push message is sent |
| `schedule.shift.assigned`   | Message for assigned shift               |
| `schedule.shift.unassigned` | Message for unassigned shift             |

## Creating a New PO Entry

1. Identify the correct **category** based on the context.
2. Use **lowercase, dot-separated** format.
3. Avoid hardcoding texts in templates or scripts—use `msgid` references.

### Example Addition

If adding a message for an expired session:

```po
msgid "auth.session.expired"
msgstr "Your session has expired. Please log in again."
```

## Updating an Existing PO File

1. Open the `.po` file in a text editor.
2. Locate the relevant `msgid`.
3. Update the corresponding `msgstr` value.
4. Save the file and commit the changes to version control.

For large-scale changes, ensure consistency with the defined hierarchical structure.

## Notes

- Always keep `msgid` keys consistent across different language files.
- Avoid using dynamic placeholders in `msgid` (e.g., `"Welcome, %s!"` should be rewritten as `"auth.welcome.message"`).
- Keep translations concise and context-aware.
