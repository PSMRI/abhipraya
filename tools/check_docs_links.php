<?php
declare(strict_types=1);

/* Check local Markdown links below docs/. External URLs and page anchors are
 * intentionally excluded because they require a browser or network request. */
$root = dirname(__DIR__);
$docsRoot = $root . DIRECTORY_SEPARATOR . 'docs';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsRoot));
$failures = 0;
$virtualDocuments = [
    'openapi-reference.md',
    'postman-collection-reference.md',
    '../openapi-reference.md',
    '../postman-collection-reference.md',
];

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    preg_match_all('/!?(?:\[[^\]]*\])\(([^)\s]+)(?:\s+[^)]*)?\)/', $contents, $matches);
    foreach ($matches[1] as $target) {
        $target = html_entity_decode((string) $target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($target === '' || $target[0] === '#' || preg_match('#^(?:https?:|mailto:|tel:|data:)#i', $target)) {
            continue;
        }
        $path = preg_split('/[?#]/', $target, 2)[0];
        if ($path === '') {
            continue;
        }
        if (in_array($path, $virtualDocuments, true)) {
            continue;
        }
        $candidate = str_starts_with($path, '/')
            ? $root . str_replace('/', DIRECTORY_SEPARATOR, $path)
            : $file->getPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($candidate) && !is_dir($candidate)) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            echo "[FAIL] {$relative} -> {$target}" . PHP_EOL;
            $failures++;
        }
    }
}

echo $failures === 0 ? "Documentation local-link check passed.\n" : "Documentation local-link check failed: {$failures} link(s).\n";
exit($failures === 0 ? 0 : 1);
