<?php
// Simple script to view the last 100 lines of the PHP error log

$errorLogFile = __DIR__ . '/error.log';

if (!file_exists($errorLogFile)) {
    echo "Error log file does not exist.";
    exit;
}

$linesToShow = 100;
$lines = [];

$fp = fopen($errorLogFile, 'r');
if ($fp) {
    $pos = -2;
    $currentLine = '';
    $lineCount = 0;
    fseek($fp, $pos, SEEK_END);
    while ($lineCount < $linesToShow) {
        $char = fgetc($fp);
        if ($char === false) {
            break;
        }
        if ($char === "\n") {
            if ($currentLine !== '') {
                array_unshift($lines, strrev($currentLine));
                $currentLine = '';
                $lineCount++;
            }
        } else {
            $currentLine .= $char;
        }
        $pos--;
        if (fseek($fp, $pos, SEEK_END) === -1) {
            if ($currentLine !== '') {
                array_unshift($lines, strrev($currentLine));
            }
            break;
        }
    }
    fclose($fp);
} else {
    echo "Unable to open error log file.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>PHP Error Log Viewer</title>
<style>
body { font-family: monospace; background: #f4f4f4; padding: 20px; }
pre { background: #222; color: #eee; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>
</head>
<body>
<h2>Last <?= $linesToShow ?> lines of PHP error log</h2>
<pre><?= htmlspecialchars(implode("\n", $lines)) ?></pre>
</body>
</html>
