<?php

/**
 * Tests for packFiles(): file names must never be read as tar options.
 *
 * Provided files keep the name they were uploaded with, and the ILIAS upload filter
 * lets a name like '--checkpoint-action=exec=sh x.sh' pass. With 'tar czf x.tgz *'
 * the shell glob hands such names to tar as options, which runs commands on the
 * ILIAS server. './*' makes every glob result start with './', so tar sees paths only.
 */

use PHPUnit\Framework\TestCase;

class TarOptionInjectionTest extends TestCase
{
    /** @var string */
    private $connector;

    /** @var string */
    private $tempDir = '';

    protected function setUp(): void
    {
        $this->connector = file_get_contents(__DIR__ . '/../../classes/class.ilExAutoScoreConnector.php');
    }

    protected function tearDown(): void
    {
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            exec('rm -rf ' . escapeshellarg($this->tempDir));
        }
    }

    /** Body of a method, from its signature to the next method signature. */
    private function method(string $name): string
    {
        $start = strpos($this->connector, 'function ' . $name . '(');
        $this->assertNotFalse($start, "method $name() not found");
        $next = strpos($this->connector, 'function ', $start + 10);
        return substr($this->connector, $start, $next === false ? null : $next - $start);
    }

    public function testPackFilesPrefixesGlobAndEscapesArchiveName(): void
    {
        $pack = $this->method('packFiles');
        $this->assertStringContainsString("escapeshellarg(\$temptar) . ' ./*'", $pack);
        $this->assertStringNotContainsString("' *'", $pack, 'a bare * glob passes file names as tar options');
    }

    public function testTempDirsAreRemovedAfterEveryServiceCall(): void
    {
        $calls = substr_count($this->connector, '$success = $this->callService(');
        $cleanups = substr_count(
            $this->connector,
            "\$success = \$this->callService(\$url, \$post, \$timeout);\n        \$this->removeTempDirs();"
        );
        $this->assertSame(3, $calls);
        $this->assertSame($calls, $cleanups, 'each send must remove the copies of the submitted files');
    }

    public function testGlobWithDotSlashDoesNotExecuteOptionNames(): void
    {
        if (!is_executable('/bin/tar')) {
            $this->markTestSkipped('/bin/tar not available');
        }

        $this->tempDir = sys_get_temp_dir() . '/exas_tar_' . uniqid('', true);
        mkdir($this->tempDir);
        file_put_contents($this->tempDir . '/x.sh', "touch PWNED\n");
        touch($this->tempDir . '/--checkpoint=1');
        touch($this->tempDir . '/--checkpoint-action=exec=sh x.sh');

        $cwd = getcwd();
        chdir($this->tempDir);
        exec('/bin/tar czf ' . escapeshellarg('out.tgz') . ' ./* 2>&1');
        chdir($cwd);

        $this->assertFileDoesNotExist($this->tempDir . '/PWNED', 'tar executed a file name as a command');
        $this->assertFileExists($this->tempDir . '/out.tgz');

        exec('/bin/tar tzf ' . escapeshellarg($this->tempDir . '/out.tgz'), $listing);
        $this->assertContains('./--checkpoint=1', $listing, 'option-like names must be archived as files');
    }
}
