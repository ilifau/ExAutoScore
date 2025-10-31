<?php

/**
 * PHPUnit Bootstrap File for ExAutoScore Tests
 *
 * This file is loaded before any tests run.
 * Use it to set up autoloading, constants, etc.
 */

// Error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone to avoid warnings
date_default_timezone_set('Europe/Berlin');

// Define ILIAS-related constants if needed
if (!defined('IL_CAL_UNIX')) {
    define('IL_CAL_UNIX', 1);
}
if (!defined('IL_CAL_DATETIME')) {
    define('IL_CAL_DATETIME', 2);
}

// Autoload ILIAS plugin classes
// Note: For full integration tests, you would need to include ILIAS's bootstrap
// For smoke tests, we just need class files to be loadable

$classesDir = __DIR__ . '/../classes';

// Simple autoloader for plugin classes
spl_autoload_register(function ($class) use ($classesDir) {
    // Convert class name to file path
    // ilExAutoScoreTask -> class.ilExAutoScoreTask.php

    $possiblePaths = [
        $classesDir . '/class.' . $class . '.php',
        $classesDir . '/models/class.' . $class . '.php',
        $classesDir . '/param/class.' . $class . '.php',
        $classesDir . '/traits/trait.' . $class . '.php',
    ];

    foreach ($possiblePaths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

echo "ExAutoScore Test Bootstrap loaded\n";
