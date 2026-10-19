<?php

/**
 * ILIAS renames uploads whose suffix is not whitelisted (aufgabe.py -> aufgabepy.sec).
 * The plugin then did not find the required file and sent an empty submission.
 */

use PHPUnit\Framework\TestCase;

class SuffixWhitelistTest extends TestCase
{
    public function testUploadIsRejectedWithAMessage(): void
    {
        $gui = file_get_contents(__DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php');
        $start = strpos($gui, 'function uploadSubmission(');
        $body = substr($gui, $start, strpos($gui, '// Collect existing files', $start) - $start);
        $this->assertStringContainsString('ilFileUtils::getValidFilename($required->getFilename()) !== $required->getFilename()', $body);
        $this->assertStringContainsString("txt('upload_error_suffix')", $body);

        foreach (['de', 'en'] as $lang) {
            $this->assertMatchesRegularExpression('/^upload_error_suffix#:#.*%s/m', file_get_contents(__DIR__ . "/../../lang/ilias_$lang.lang"));
        }
    }
}
