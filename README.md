# ExAutoScore

ILIAS plugin for StudOn: automated scoring of programming assignments in
exercises by an external grading service.

## What it does
- Two additional assignment types: **individual** (type id 101) and **team** (102).
- Lecturers upload the files of the grading environment (e.g. a Dockerfile),
  define the files students have to submit and set the assignment up on the
  grading service. An example solution can be sent as a test.
- Students upload the required files; they are sent to the grading service
  right away. A short status and message are shown immediately; points,
  extended feedback and feedback files become visible after the deadline.
- After the deadline the result is published into the exercise grades once,
  never overwriting a grade given by a tutor.
- Team changes (members added or removed) are handled: submissions are copied
  or removed so every team and every former member keeps a complete set.
- Email notifications on failures and an alert to the operators when the
  grading service stops accepting submissions.

## Requirements
| Branch        | StudOn       | ILIAS       | PHP       | Plugin version |
|---------------|--------------|-------------|-----------|----------------|
| `main-ilias9` | StudOn 9     | 9.12 – 9.x  | 8.1 – 8.2 | 0.x            |
| `dev-ilias10` | StudOn 10    | 10.x        | 8.2 – 8.3 | 10.x           |
| `dev-ilias11` | StudOn 11    | 11.x        | 8.3 – 8.4 | 11.x           |

- A StudOn core with the patch **`fau: exAssHook`** (plugin slot
  `Modules/Exercise/AssignmentHook`). Vanilla ILIAS has no plugin slot in the
  exercise component, so the plugin does not run there.
- The grading service, reachable from ILIAS; the service in turn must be able
  to call the callback URL (see below).
- `tar`/`untar` on the ILIAS server (configurable commands).

## Installation

### StudOn 9
```bash
cd <ILIAS>/Customizing/global/plugins/Modules/Exercise/AssignmentHook
git clone -b main-ilias9 https://github.com/ilifau/ExAutoScore.git ExAutoScore
cd <ILIAS>
composer install --no-dev
php setup/setup.php update
```
Then *Administration → Extending ILIAS → Plugins*: install and activate ExAutoScore.

### StudOn 10 / 11
The plugin directory is below `public/`:
```bash
cd <ILIAS>/public/Customizing/global/plugins/Modules/Exercise/AssignmentHook
git clone -b dev-ilias11 https://github.com/ilifau/ExAutoScore.git ExAutoScore   # or dev-ilias10
cd <ILIAS>
composer install --no-dev
php cli/setup.php update
```
From ILIAS 10 on the setup script is `cli/setup.php` (`setup/setup.php` no
longer exists). `composer install` builds the setup artifacts (plugin slots,
control structure, plugin list) automatically, so no separate `build` is
needed. Then install and activate the plugin in the administration as above.

### Updates
A new plugin version deactivates the plugin until *Update* is clicked in the
plugin administration. Plan updates of the code and the click together.

## Configuration
*Administration → Plugins → ExAutoScore → Configure*

| Setting | Purpose |
|---|---|
| `service_assignment_url` | URL of the grading service to set up assignments |
| `service_task_url` | URL of the grading service to send submissions |
| `service_api_key` | API key of the grading service |
| `service_timeout` | Timeout for calls to the service (seconds) |
| `creator_roles` | Role IDs allowed to create assignments of these types (admins always can) |
| `tar_command` / `untar_command` | Commands to pack and unpack files sent to the service |
| `enable_failure_mails` | Mails to lecturers when grading fails |
| `enable_admin_failure_mails` / `admin_failure_mails` | Mails to operators, incl. service outage alerts |
| `enable_debug_logs` | Store debug output of the service (per assignment switchable) |

## Callback URL
The grading service sends results to
```
<ILIAS URL>/Customizing/global/plugins/Modules/Exercise/AssignmentHook/ExAutoScore/results.php
```
The URL is the same for StudOn 9, 10 and 11 (in 10/11 `public/` is the web root).

## Upgrading StudOn 9 → 10 / 11
1. The StudOn core must contain `fau: exAssHook` before the plugin is updated.
2. Run the ILIAS update; ILIAS moves existing submissions and feedback files
   into the resource storage.
3. Move the plugin below `public/Customizing/…` and check out `dev-ilias10`
   or `dev-ilias11`, then `composer install --no-dev`, `php cli/setup.php update`
   and *Update* in the plugin administration.

The callback URL does not change, the grading service needs no new
configuration. An upgrade with existing ExAutoScore assignments has not been
tested yet (see test scenario J10).

## Tests
Source-inspection tests (no running ILIAS needed); the plugin ships no
PHPUnit, use the one of an ILIAS core:
```bash
php <ILIAS>/vendor/composer/vendor/bin/phpunit tests/
```
Manual test scenarios: `tests/TEST_SCENARIOS_ExAutoScore.md`.

## Changelog
See `CHANGELOG.md`.

## License and authors
GPLv3, see `LICENSE`.
Institut für Lern-Innovation, Friedrich-Alexander-Universität Erlangen-Nürnberg —
Fred Neumann, Cornel Musielak.
