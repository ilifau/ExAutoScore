<?php

/**
 * Regression tests for the port from ILIAS 9 to 10 and 11.
 * Source-inspection tests, per project convention (no ILIAS runtime needed).
 */

use PHPUnit\Framework\TestCase;

class PortRegressionTest extends TestCase
{
    private const ILIAS_VERSION = 11;

    /** @var string */
    private $base;

    protected function setUp(): void
    {
        $this->base = __DIR__ . '/../../';
    }

    /** @return array<string, string> relative path => source of all plugin PHP files */
    private function sources(): array
    {
        $files = array_merge(
            glob($this->base . 'classes/*.php'),
            glob($this->base . 'classes/*/*.php'),
            [$this->base . 'plugin.php', $this->base . 'results.php']
        );
        $sources = [];
        foreach ($files as $file) {
            $sources[substr($file, strlen($this->base))] = file_get_contents($file);
        }
        return $sources;
    }

    private function assertNowhere(string $pattern, string $message): void
    {
        foreach ($this->sources() as $file => $source) {
            $this->assertDoesNotMatchRegularExpression($pattern, $source, "$file: $message");
        }
    }

    // --- all ILIAS versions ---

    public function testNoRemovedIlUtilFileHelpers(): void
    {
        $this->assertNowhere('/ilUtil::(getASCIIFilename|ilTempnam)\s*\(/', 'moved to ilFileUtils since ILIAS 9');
    }

    public function testTablesAreDroppedOnUninstall(): void
    {
        $plugin = file_get_contents($this->base . 'classes/class.ilExAutoScorePlugin.php');
        $this->assertStringNotContainsString('function uninstallCustom(', $plugin, 'ilPlugin never calls uninstallCustom()');
        $this->assertMatchesRegularExpression(
            '/function afterUninstall\(\): void\s*\{.*dropTable\(.exautoscore_assignment/s',
            $plugin,
            'afterUninstall() must drop the plugin tables'
        );
    }

    public function testPluginVersionRangeMatchesBranch(): void
    {
        $plugin = file_get_contents($this->base . 'plugin.php');
        $v = self::ILIAS_VERSION;
        $this->assertMatchesRegularExpression('/\$ilias_max_version\s*=\s*"' . $v . '\.999"/', $plugin);
        $this->assertMatchesRegularExpression('/\$version\s*=\s*"' . ($v >= 10 ? $v . '\.' : '0\.') . '/', $plugin);
    }

    // --- ILIAS 10 and later ---

    public function testNoRemovedSubmissionFileApi(): void
    {
        $this->assertNowhere('/ubmission->(getFiles|uploadFile)\s*\(/', 'removed in ILIAS 10, use ilExAutoScoreSubmissionFiles');
        $this->assertNowhere('/ilFSStorageExercise/', 'removed in ILIAS 10, files are in the resource storage');
    }

    public function testSubmissionFilesAdapterProvidesAllOperations(): void
    {
        $adapter = file_get_contents($this->base . 'classes/class.ilExAutoScoreSubmissionFiles.php');
        foreach (['getFiles', 'getSize', 'copyToTemp', 'addUpload', 'addLocalFile', 'replaceFeedbackFiles', 'deleteFeedbackFiles'] as $method) {
            $this->assertStringContainsString("public static function $method(", $adapter);
        }
    }

    public function testNoCoreRequires(): void
    {
        $this->assertNowhere('/(require|include)(_once)?\s*\(?\s*[\'"](\.\/)?(Modules|Services)\//', 'core paths moved to components/ILIAS in 10, use the autoloader');
    }

    public function testNoLegacyModal(): void
    {
        $this->assertNowhere('/ilModalGUI/', 'deprecated in 10 and removed in 11');
    }

    public function testRowTemplatesUseNormalizedPath(): void
    {
        foreach (['ProvidedFiles', 'RequiredFiles'] as $table) {
            $source = file_get_contents($this->base . "classes/class.ilExAutoScore{$table}TableGUI.php");
            $this->assertStringContainsString(
                'realpath($this->plugin->getDirectory())',
                $source,
                "$table: getDirectory() is not normalized in 10, ilTemplate would not find the row template"
            );
        }
    }

    public function testResultsEndpointBootsFromWebroot(): void
    {
        $results = file_get_contents($this->base . 'results.php');
        $this->assertStringContainsString("chdir(__DIR__ . '/../../../../../../../')", $results);
        $this->assertStringContainsString("require_once '../vendor/composer/vendor/autoload.php'", $results);
        $this->assertStringContainsString('ilContext::init(ilContext::CONTEXT_RSS)', $results);
    }

    // --- ILIAS 11 and later ---

    public function testResultsEndpointUsesEntryPoint(): void
    {
        $results = file_get_contents($this->base . 'results.php');
        $this->assertStringContainsString("require_once '../artifacts/bootstrap_default.php'", $results);
        $this->assertStringContainsString("entry_point('ILIAS Legacy Initialisation Adapter')", $results);
        $this->assertStringNotContainsString('ilInitialisation::initILIAS()', $results, 'entry_point() initialises ILIAS itself');
    }

    public function testNoOwnDeclarationOfTypedTraitProperties(): void
    {
        $gui = file_get_contents($this->base . 'classes/class.ilExAssTypeAutoScoreBaseGUI.php');
        $this->assertDoesNotMatchRegularExpression(
            '/protected\s+[^;]*\$(submission|exercise)\b/',
            $gui,
            'ilExAssignmentTypeGUIBase declares typed $submission/$exercise in 11, a different declaration is fatal'
        );
    }

    public function testNoImplicitlyNullableParameters(): void
    {
        $this->assertNowhere(
            '/(^|[(,])\s*\\\\?il[A-Za-z]+\s+\$[A-Za-z_]+\s*=\s*null/m',
            'implicitly nullable parameters are deprecated in PHP 8.4, write ?Type'
        );
    }
}
