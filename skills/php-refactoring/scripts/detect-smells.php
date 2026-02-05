<?php

/**
 * Detect common code smells in PHP files.
 *
 * Usage: php detect-smells.php <file-or-directory>
 */

$target = $argv[1] ?? '.';

$smells = [
    'long-method' => [
        'pattern' => '/function\s+\w+\s*\([^)]*\)[^{]*\{/',
        'threshold' => 30, // lines
        'description' => 'Method exceeds 30 lines',
    ],
    'long-parameter-list' => [
        'pattern' => '/function\s+\w+\s*\(([^)]+)\)/',
        'threshold' => 4, // parameters
        'description' => 'Method has more than 4 parameters',
    ],
    'god-class' => [
        'pattern' => '/class\s+\w+/',
        'threshold' => 300, // lines
        'description' => 'Class exceeds 300 lines',
    ],
    'magic-numbers' => [
        'pattern' => '/[^a-zA-Z_$]\b\d{2,}\b/',
        'description' => 'Magic number found (consider using named constants)',
    ],
    'deep-nesting' => [
        'threshold' => 4, // levels
        'description' => 'Code is nested more than 4 levels deep',
    ],
];

/**
 * Scan a PHP file for code smells.
 */
function scanFile(string $file, array $smells): array
{
    $findings = [];
    $content = file_get_contents($file);
    if ($content === false) {
        return $findings;
    }

    $lines = explode("\n", $content);
    $lineCount = count($lines);

    // Check file length (god class indicator)
    if ($lineCount > 300 && preg_match('/class\s+\w+/', $content)) {
        $findings[] = [
            'smell' => 'god-class',
            'line' => 1,
            'message' => "File has {$lineCount} lines (threshold: 300)",
        ];
    }

    // Check for deep nesting
    $maxNesting = 0;
    $currentNesting = 0;
    foreach ($lines as $i => $line) {
        $currentNesting += substr_count($line, '{') - substr_count($line, '}');
        if ($currentNesting > $maxNesting) {
            $maxNesting = $currentNesting;
        }
        if ($currentNesting > 4) {
            $findings[] = [
                'smell' => 'deep-nesting',
                'line' => $i + 1,
                'message' => "Nesting level: {$currentNesting} (threshold: 4)",
            ];
        }
    }

    return $findings;
}

// Execute
$files = [];
if (is_file($target)) {
    $files[] = $target;
} elseif (is_dir($target)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

foreach ($files as $file) {
    $findings = scanFile($file, $smells);
    if (!empty($findings)) {
        echo "\n{$file}:\n";
        foreach ($findings as $finding) {
            echo "  Line {$finding['line']}: [{$finding['smell']}] {$finding['message']}\n";
        }
    }
}
