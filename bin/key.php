<?php

declare(strict_types=1);

/** @var \SearPHP\Config $config */
$config = require dirname(__DIR__) . '/bootstrap.php';

$write = in_array('--write', $argv, true);
$label = '';
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        $label = trim($arg);
        break;
    }
}

$key = 'sk_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$hash = hash('sha256', $key);

if ($write) {
    $file = (string) $config->get('security.api_keys_file', dirname(__DIR__) . '/config/api_keys.txt');
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create directory: {$dir}\n");
        exit(1);
    }

    $prefix = $label !== '' ? '# ' . preg_replace('/[^\w .@-]/', '', $label) . "\n" : '';
    $line = $prefix . 'sha256:' . $hash . "\n";

    if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
        fwrite(STDERR, "Cannot write key file: {$file}\n");
        exit(1);
    }
    @chmod($file, 0600);

    echo "API key (shown once):\n{$key}\n\n";
    echo "Stored hash in: {$file}\n";
    echo "Use it with: Authorization: Bearer {$key}\n";
    exit(0);
}

echo "API key: {$key}\n";
echo "Add this line to config/api_keys.txt:\n";
echo "sha256:{$hash}\n";
