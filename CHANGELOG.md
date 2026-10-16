# Changelog

Version scheme: `0.x` for StudOn 9 (branch `main-ilias9`), `10.x` for
StudOn 10 (`dev-ilias10`), `11.x` for StudOn 11 (`dev-ilias11`).

## 11.0.1 (2026-10-15) — StudOn 11
Fixes of 0.4.1 and the API key check (StudOn 9), adapted to ILIAS 11:
- Security: file names of provided files are no longer passed to `tar` as
  options; temporary copies of submissions are removed after sending.
- Security: the extended feedback HTML is filtered with HTMLPurifier;
  service messages are escaped.
- Security: `results.php` only accepts results that carry the configured API
  key in the `X-Autocor-Key` header. Deploy the grading service that sends
  this header first, otherwise every result is rejected.
- Fix: results, extended feedback and grades follow the effective deadline
  of ILIAS. For the AutoScore team type the individual deadline of the team
  is read by the plugin, because the ILIAS 10+ core resolves teams only for
  upload team assignments.
- Fix: the reset before the deadline only clears a grade the plugin wrote
  itself; a tutor grade is never removed. Since ILIAS 10 service and tutor
  feedback files share one collection, the reset removes both.
- Small fixes: debug log time zone, removed developer command from the
  configuration.
- Requires a plugin update (new column `published_note_hash`).

Preparation for ILIAS 12 (legacy UI components are removed there):
- Action menus in the file tables use the UI framework dropdown
  (`ilAdvancedSelectionListGUI` is already removed in ILIAS 12).
- Toolbar buttons use UI framework buttons instead of `ilLinkButton`.
- "Delete submission" and "Send all tasks" ask for confirmation in a dialog
  (UI framework interruptive modal) instead of a separate page, and only
  accept POST requests.
- The plugin configuration is a UI framework form. Creator roles are entered
  as tags (global roles are suggested, any role title can be typed as in
  ILIAS 9); unknown role titles are rejected with a message.
- The tables of provided and required files are UI framework data tables
  (sortable, paginated). "Edit file" is a row action; "Delete files" asks in
  a dialog and only accepts POST requests.

## 11.0.0 (2026-10-05) — StudOn 11
Port of 10.0.0 to ILIAS 11. Requires a StudOn 11 core with the patch
`fau: exAssHook`.
- Compatible with the typed properties of `ilExAssignmentTypeGUIBase`
  (ILIAS 11); the plugin no longer declares `$submission`/`$exercise`.
- `results.php` boots via `entry_point('ILIAS Legacy Initialisation Adapter')`.
- PHP 8.4: no implicitly nullable parameters.
- `plugin.php`: ILIAS 11.0 – 11.999.

## 10.0.0 (2026-10-05) — StudOn 10
Port of 0.4.0 to ILIAS 10. Requires a StudOn 10 core with the patch
`fau: exAssHook` (plugin slot `Modules/Exercise/AssignmentHook`).
- Submitted files and tutor feedback files are stored in the ILIAS
  resource storage (IRSS) instead of the file system
  (new class `ilExAutoScoreSubmissionFiles`).
- `results.php` (callback of the grading service) boots the ILIAS 10 way.
- Feedback and debug-log dialogs use the UI framework modal instead of the
  removed-in-11 `ilModalGUI`.
- Row templates of the file tables load from the normalized plugin path.
- No core `require_once`s any more (autoloader).
- `plugin.php`: ILIAS 10.0 – 10.999.

## 0.4.0 (2026-10-05) — StudOn 9
- Fix: feedback files returned by the grading service were not stored
  (`ilUtil::getASCIIFilename()` no longer exists since ILIAS 9).
- Fix: copying a submission to a new team member failed
  (`ilUtil::ilTempnam()` no longer exists since ILIAS 9).
- Fix: uninstalling the plugin left the `exautoscore_*` tables in the
  database (cleanup moved from `uninstallCustom()` to `afterUninstall()`).
- New version scheme per ILIAS version (see above).

## 0.3.9 (2026-09-14)
- Setting up an assignment again reuses the assignment on the grading
  service instead of creating a new one (and a new Docker image).
- Dockerfile hint: four cases where it misled or did not show.

## 0.3.8 (2026-08-07)
- Lecturers are warned when a Dockerfile wastes disk space on the grading
  server.

## 0.3.7 (2026-07-15)
- Reworked email notifications, localized per recipient, global
  kill-switch, sample-solution failures are reported.
- Operators are alerted when the grading service stops accepting
  submissions; the alert never breaks a working submission.

## 0.3.6 (2026-05-29)
- One rule for auto-publishing results; fixes for tutor grades being
  overwritten, zero-point failures, teams and paginated lists.

## 0.3.5 (2026-05-29)
- Fix wrong marks.

## 0.3.4 (2026-05-28)
- Auto-publish never overwrites tutor feedback (published once per result).

## 0.3.3 (2026-05-12)
- New assignment setting "hide sample solution in overview".

Older versions: see the git history.
