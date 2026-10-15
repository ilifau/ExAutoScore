<?php

/**
 * ActiveRecord::find() returns null for an unknown id. Calling a method on the result
 * without a check ends in a fatal error when the 'id' URL parameter is invalid.
 */

use PHPUnit\Framework\TestCase;

class InvalidFileIdTest extends TestCase
{
    public static function guiProvider(): array
    {
        return [
            'required files' => ['ilExAutoScoreRequiredFilesGUI'],
            'provided files' => ['ilExAutoScoreProvidedFilesGUI'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('guiProvider')]
    public function testFindResultIsCheckedForNull(string $class): void
    {
        $source = file_get_contents(__DIR__ . '/../../classes/class.' . $class . '.php');
        $count = preg_match_all('/\$file = \w+::find\(\$id\);\s*\n\s*if \(\$file === null \|\|/', $source);
        $total = substr_count($source, '::find($id)');
        $this->assertGreaterThan(0, $total);
        $this->assertSame($total, $count, "find() result used without null check in $class");
    }
}
