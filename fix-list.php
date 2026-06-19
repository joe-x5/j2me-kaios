<?php
// Define the directory containing the jars
$jarsDir = __DIR__ . '/jars';

// Define the path to the output file
$outputFile = __DIR__ . '/list.json';

// Check if the directory exists
if (!is_dir($jarsDir)) {
    die("Jars directory does not exist.");
}

// Get all .jar files in the directory
$jarFiles = array_filter(glob($jarsDir . '/*.jar'), 'is_file');

// Prepare lines to write
$lines = [];

foreach ($jarFiles as $filePath) {
    // Get the relative path
    $relativePath = str_replace(__DIR__ . '/', '', $filePath);
    $lines[] = $relativePath;
}

// Write lines to the file
file_put_contents($outputFile, implode("\n", $lines));

echo "list.json has been updated.";
?>