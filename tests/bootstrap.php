<?php

/**
 * PHPUnit Bootstrap File for ExAutoScore Tests
 *
 * This file is loaded before any tests run.
 * For smoke tests, we don't actually load ILIAS classes - we just check file existence and content.
 */

// Error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone to avoid warnings
date_default_timezone_set('Europe/Berlin');

// Note: For smoke tests, we don't need to load ILIAS or plugin classes
// We only verify file existence and content by reading source files
// This allows tests to run without ILIAS installation

echo "ExAutoScore Test Bootstrap loaded (smoke test mode - no class loading)\n";
