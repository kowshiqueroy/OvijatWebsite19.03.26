<?php
/**
 * Find Hard Deletes in Project
 */
$directory = new RecursiveDirectoryIterator('.');
$iterator = new RecursiveIteratorIterator($directory);
$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

echo "--- SQL DELETE Statements Found ---\n";
foreach ($regex as $info) {
    $file = $info[0];
    if (str_contains($file, 'scratch/') || str_contains($file, '.gemini')) {
        continue;
    }
    
    $content = file_get_contents($file);
    if (preg_match_all('/(delete\s+from\s+[a-z0-9_`]+)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
        echo "File: $file\n";
        foreach ($matches[1] as $match) {
            $offset = $match[1];
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            echo "  Line $line: {$match[0]}\n";
        }
    }
}
echo "--- Search Complete ---\n";
