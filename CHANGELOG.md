# Changelog

Version scheme: `0.x` for StudOn 9 (branch `dev-ilias9`), `10.x` for
StudOn 10 (`dev-ilias10`), `11.x` for StudOn 11 (`dev-ilias11`).

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
