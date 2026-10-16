<?php

/**
 * An unchecked checkbox is not part of the POST data. Reading it without a check
 * aborts the request in ILIAS, so editing a provided file that is not public saved nothing.
 */

use PHPUnit\Framework\TestCase;

class UncheckedCheckboxTest extends TestCase
{
    public function testPublicFlagIsReadSafely(): void
    {
        $source = file_get_contents(__DIR__ . '/../../classes/class.ilExAutoScoreProvidedFilesGUI.php');
        $this->assertDoesNotMatchRegularExpression("/\\(bool\\) \\\$params\\['exautoscore_file_public'\\]/", $source);
        $this->assertDoesNotMatchRegularExpression("/\\\$params\\['exautoscore_file_(description|purpose)'\\]\\)/", $source);
    }
}
