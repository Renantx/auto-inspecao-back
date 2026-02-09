<?php
declare(strict_types=1);

$possiblePaths = [
    dirname(__DIR__) . '/.env',          // raiz do projeto (local)
    dirname(__DIR__, 2) . '/.env',       // um nível acima (quando projeto está dentro de public_html)
];

$envPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $envPath = $path;
        break;
    }
}

if ($envPath === null) {
    throw new RuntimeException('.env não encontrado');
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (!str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);

    // remove aspas se tiver "valor"
    $value = trim($value, "\"'");

    putenv("$key=$value");
    $_ENV[$key] = $value;
}
