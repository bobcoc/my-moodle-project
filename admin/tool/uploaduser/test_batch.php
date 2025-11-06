<?php
/**
 * Debug script for picture_batch.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Testing picture_batch.php dependencies</h1>";

// Test 1: Check config
echo "<h2>Test 1: Loading config.php</h2>";
try {
    require(__DIR__.'/../../../config.php');
    echo "✓ config.php loaded successfully<br>";
    echo "CFG->wwwroot: " . $CFG->wwwroot . "<br>";
} catch (Exception $e) {
    echo "✗ Error loading config.php: " . $e->getMessage() . "<br>";
    die();
}

// Test 2: Check required libraries
echo "<h2>Test 2: Loading required libraries</h2>";
try {
    require_once($CFG->libdir.'/adminlib.php');
    echo "✓ adminlib.php loaded<br>";
} catch (Exception $e) {
    echo "✗ Error loading adminlib.php: " . $e->getMessage() . "<br>";
}

try {
    require_once($CFG->libdir.'/gdlib.php');
    echo "✓ gdlib.php loaded<br>";
} catch (Exception $e) {
    echo "✗ Error loading gdlib.php: " . $e->getMessage() . "<br>";
}

// Test 3: Check form class
echo "<h2>Test 3: Loading form class</h2>";
try {
    require_once(__DIR__.'/picture_batch_form.php');
    echo "✓ picture_batch_form.php loaded<br>";
    
    if (class_exists('admin_uploadpicture_batch_form')) {
        echo "✓ admin_uploadpicture_batch_form class exists<br>";
    } else {
        echo "✗ admin_uploadpicture_batch_form class not found<br>";
    }
} catch (Exception $e) {
    echo "✗ Error loading picture_batch_form.php: " . $e->getMessage() . "<br>";
}

// Test 4: Check permissions
echo "<h2>Test 4: Checking permissions and context</h2>";
try {
    $context = context_system::instance();
    echo "✓ System context loaded<br>";
    
    if (is_siteadmin()) {
        echo "✓ You are a site admin<br>";
    } else {
        echo "⚠ You are NOT a site admin<br>";
    }
    
    if (has_capability('tool/uploaduser:uploaduserpictures', $context)) {
        echo "✓ You have uploaduserpictures capability<br>";
    } else {
        echo "✗ You do NOT have uploaduserpictures capability<br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking permissions: " . $e->getMessage() . "<br>";
}

// Test 5: Check language strings
echo "<h2>Test 5: Checking language strings</h2>";
$required_strings = array(
    'uploadpicturesbatch',
    'uploadpicturesbatch_help',
    'uploadpicture_overwrite',
    'uploadpictures',
    'uploadpicture_cannotmovezip',
    'uploadpicture_cannotunzip',
    'uploadpicture_cannotprocessdir',
    'processingfiles',
    'uploadstats',
    'totalfiles',
    'usersupdated',
    'picturesskipped',
    'usersnotfound',
    'errors',
    'uploadmore',
    'uploadpicture_usernotfound',
    'uploadpicture_userskipped',
    'uploadpicture_userupdated',
    'uploadpicture_cannotsave'
);

$missing_strings = array();
foreach ($required_strings as $string) {
    $value = get_string($string, 'tool_uploaduser');
    if (strpos($value, '[[') === 0) {
        $missing_strings[] = $string;
        echo "✗ Missing string: $string<br>";
    }
}

if (empty($missing_strings)) {
    echo "✓ All required language strings are defined<br>";
} else {
    echo "<strong>⚠ " . count($missing_strings) . " language strings are missing</strong><br>";
}

// Test 6: Check functions
echo "<h2>Test 6: Checking required functions</h2>";
$functions = array('process_new_icon', 'make_temp_directory', 'remove_dir', 'get_file_packer');
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✓ Function $func exists<br>";
    } else {
        echo "✗ Function $func NOT found<br>";
    }
}

echo "<h2>All tests completed</h2>";
echo "<p><a href='picture_batch.php'>Go to picture_batch.php</a></p>";
