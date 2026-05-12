<?php

/**
 * Smoke tests for the "hide sample solution in overview" setting.
 *
 * Lets a lecturer suppress the sample-solution (= required files) display in
 * the exercise overview / submission feedback, regardless of deadline. The
 * setting is stored per assignment in exautoscore_assignment.hide_sample_solution
 * and edited in the plugin's per-assignment "ExAutoScore settings" tab
 * (ilExAutoScoreSettingsGUI).
 *
 * These tests follow the project's source-inspection convention (no ILIAS
 * runtime needed).
 */

use PHPUnit\Framework\TestCase;

class HideSampleSolutionTest extends TestCase
{
    /** @var string */
    private $baseGui;
    /** @var string */
    private $settingsGui;
    /** @var string */
    private $assignmentModel;
    /** @var string */
    private $dbUpdate;

    protected function setUp(): void
    {
        $base     = __DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php';
        $settings = __DIR__ . '/../../classes/class.ilExAutoScoreSettingsGUI.php';
        $model    = __DIR__ . '/../../classes/models/class.ilExAutoScoreAssignment.php';
        $sql      = __DIR__ . '/../../sql/dbupdate.php';
        $this->assertFileExists($base);
        $this->assertFileExists($settings);
        $this->assertFileExists($model);
        $this->assertFileExists($sql);
        $this->baseGui         = file_get_contents($base);
        $this->settingsGui     = file_get_contents($settings);
        $this->assignmentModel = file_get_contents($model);
        $this->dbUpdate        = file_get_contents($sql);
    }

    // -------------------------------------------------------------------
    // DB migration
    // -------------------------------------------------------------------

    public function testDbUpdateAddsHideSampleSolutionColumn(): void
    {
        $this->assertStringContainsString(
            "tableColumnExists('exautoscore_assignment', 'hide_sample_solution')",
            $this->dbUpdate,
            'dbupdate.php must add the hide_sample_solution column idempotently'
        );
        $this->assertMatchesRegularExpression(
            '/addTableColumn\(\s*"exautoscore_assignment",\s*\'hide_sample_solution\'/s',
            $this->dbUpdate,
            'dbupdate.php must call addTableColumn for hide_sample_solution'
        );
    }

    // -------------------------------------------------------------------
    // Model
    // -------------------------------------------------------------------

    public function testModelHasHideSampleSolutionProperty(): void
    {
        $this->assertMatchesRegularExpression(
            '/@con_has_field\s+true.*?@con_fieldtype\s+integer.*?protected \?bool \$hide_sample_solution/s',
            $this->assignmentModel,
            'Model must declare hide_sample_solution as a persisted integer/bool field'
        );
    }

    public function testModelHasGetterAndSetter(): void
    {
        $this->assertStringContainsString(
            'public function getHideSampleSolution(): bool',
            $this->assignmentModel,
            'Model must expose getHideSampleSolution()'
        );
        $this->assertStringContainsString(
            'public function setHideSampleSolution(bool $hide_sample_solution): void',
            $this->assignmentModel,
            'Model must expose setHideSampleSolution()'
        );
        $this->assertStringContainsString(
            'return (bool) $this->hide_sample_solution;',
            $this->assignmentModel,
            'getHideSampleSolution() must cast to bool'
        );
    }

    // -------------------------------------------------------------------
    // Settings GUI (the per-assignment "ExAutoScore settings" tab)
    // -------------------------------------------------------------------

    public function testSettingsFormAddsCheckbox(): void
    {
        // initSettingsForm() must add a checkbox with the documented post var,
        // pre-checked from the stored flag.
        $this->assertMatchesRegularExpression(
            '/ilCheckboxInputGUI\(.*?\'exautoscore_hide_sample_solution\'/s',
            $this->settingsGui,
            'ilExAutoScoreSettingsGUI must add a hide_sample_solution checkbox'
        );
        $this->assertStringContainsString(
            'setChecked($assAuto->getHideSampleSolution())',
            $this->settingsGui,
            'The checkbox must be pre-checked from the stored flag'
        );
    }

    public function testSettingsSavePersistsValue(): void
    {
        // saveSettings() must persist the checkbox into the assignment record.
        $this->assertMatchesRegularExpression(
            '/setHideSampleSolution\(\s*isset\(\$params\[\'exautoscore_hide_sample_solution\'\]\)\s*&&\s*\(bool\)\s*\$params\[\'exautoscore_hide_sample_solution\'\]\s*\)/s',
            $this->settingsGui,
            'saveSettings() must call setHideSampleSolution() with the posted value'
        );
    }

    public function testFormHooksRemainStubs(): void
    {
        // The setting deliberately lives in the plugin tab, NOT the standard
        // assignment edit form — so the exAssHook form hooks stay empty.
        $this->assertStringContainsString(
            'public function addEditFormCustomProperties(ilPropertyFormGUI $form, $exercise_id = null, $assignment_id = null): void {}',
            $this->baseGui,
            'addEditFormCustomProperties must remain an empty stub'
        );
        $this->assertStringContainsString(
            'public function importFormToAssignment(ilExAssignment $ass, ilPropertyFormGUI $form): void {}',
            $this->baseGui,
            'importFormToAssignment must remain an empty stub'
        );
        $this->assertStringNotContainsString(
            'exautoscore_hide_sample_solution',
            // strip the helper doc comment region is overkill; the hooks above
            // are empty so the post var must not appear anywhere in baseGui:
            $this->baseGui,
            'The hide_sample_solution post var must not appear in BaseGUI (it lives in the settings GUI)'
        );
    }

    // -------------------------------------------------------------------
    // Display gate
    // -------------------------------------------------------------------

    public function testShouldShowSampleSolutionHelperExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/protected function shouldShowSampleSolution\(ilExAssignment \$ass\): bool\s*\{.*?return !\$scoreAss->getHideSampleSolution\(\);/s',
            $this->baseGui,
            'shouldShowSampleSolution() must invert the stored flag'
        );
    }

    public function testAllOverviewSpotsAreGated(): void
    {
        // Count the call sites — there are four display spots that show the
        // sample solution (3 getOverview* methods + the TUTOR_EVAL section in
        // buildSubmissionPropertiesAndActions). Each must consult the helper.
        $calls = preg_match_all(
            '/\$this->shouldShowSampleSolution\(/',
            $this->baseGui
        );
        // 1 definition use inside the helper doc is comment-only; the actual
        // call sites: 4 display spots. Allow >= 4 to be future-proof.
        $this->assertGreaterThanOrEqual(
            4,
            $calls,
            'All four sample-solution display spots must call shouldShowSampleSolution()'
        );
    }

    public function testGeneralFeedbackEarlyReturns(): void
    {
        // getOverviewGeneralFeedback had no gate at all before — now it must
        // bail out immediately when the lecturer hid the sample solution.
        $this->assertMatchesRegularExpression(
            '/getOverviewGeneralFeedback\([^)]*\): void\s*\{\s*if \(!\$this->shouldShowSampleSolution\(\$a_assignment\)\)\s*\{\s*return;/s',
            $this->baseGui,
            'getOverviewGeneralFeedback() must early-return when sample solution is hidden'
        );
    }

    // -------------------------------------------------------------------
    // Lang strings
    // -------------------------------------------------------------------

    public function testLangStringsExist(): void
    {
        $de = file_get_contents(__DIR__ . '/../../lang/ilias_de.lang');
        $en = file_get_contents(__DIR__ . '/../../lang/ilias_en.lang');
        foreach (['de' => $de, 'en' => $en] as $label => $content) {
            $this->assertStringContainsString(
                'hide_sample_solution#:#',
                $content,
                "lang/ilias_{$label}.lang must define hide_sample_solution"
            );
            $this->assertStringContainsString(
                'hide_sample_solution_info#:#',
                $content,
                "lang/ilias_{$label}.lang must define hide_sample_solution_info"
            );
        }
    }

    public function testVersionBumped(): void
    {
        $plugin = file_get_contents(__DIR__ . '/../../plugin.php');
        // DB schema changed -> version must have moved past 0.3.2
        $this->assertDoesNotMatchRegularExpression(
            '/\$version\s*=\s*"0\.3\.2"/',
            $plugin,
            'plugin.php version must be bumped past 0.3.2 because the DB schema changed'
        );
    }
}
