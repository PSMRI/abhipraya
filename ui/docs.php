<?php
declare(strict_types=1);

$documents = [
    'README' => 'README.md',
    'SUMMARY' => 'SUMMARY.md',
    'user-guide' => 'user-guide.md',
    'developer-guide' => 'developer-guide.md',
    'security' => 'security.md',
    'dpg-readiness' => 'dpg-readiness.md',
];
$key = (string) ($_GET['document'] ?? 'README');
if (!isset($documents[$key])) {
    http_response_code(404);
    exit('Document not found.');
}
$source = dirname(__DIR__) . '/docs/' . $documents[$key];
$markdown = (string) file_get_contents($source);

function docsInline(string $value): string
{
    $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return preg_replace_callback('/\[([^\]]+)\]\(([^\)]+)\)/', static function (array $match): string {
        if (preg_match('#^https?://#i', $match[2])) {
            return '<a href="' . $match[2] . '" rel="noopener noreferrer" target="_blank">' . $match[1] . '</a>';
        }
        $href = preg_replace('/\.md$/', '', $match[2]);
        return '<a href="/docs/' . rawurlencode($href) . '.md">' . $match[1] . '</a>';
    }, $value) ?? $value;
}

function docsRender(string $markdown): string
{
    $output = '';
    $inList = false;
    foreach (preg_split('/\R/', $markdown) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            if ($inList) { $output .= '</ul>'; $inList = false; }
            continue;
        }
        if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $match)) {
            if ($inList) { $output .= '</ul>'; $inList = false; }
            $level = strlen($match[1]);
            $output .= '<h' . $level . '>' . docsInline($match[2]) . '</h' . $level . '>';
            continue;
        }
        if (preg_match('/^-\s+(.+)$/', $trimmed, $match)) {
            if (!$inList) { $output .= '<ul>'; $inList = true; }
            $output .= '<li>' . docsInline($match[1]) . '</li>';
            continue;
        }
        if (str_starts_with($trimmed, '|')) {
            continue;
        }
        if ($inList) { $output .= '</ul>'; $inList = false; }
        $output .= '<p>' . docsInline($trimmed) . '</p>';
    }
    return $output . ($inList ? '</ul>' : '');
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= htmlspecialchars($key) ?> | Abhipraya documentation</title>
<link rel="stylesheet" href="/ui/assets/css/reset.css"><link rel="stylesheet" href="/ui/assets/css/variables.css"><link rel="stylesheet" href="/ui/assets/css/global.css"><link rel="stylesheet" href="/ui/assets/css/app-shell.css">
<style>body{background:#f7f9fc;color:#12213a;font-family:Inter,"Segoe UI",Arial,sans-serif}.docs-head{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px clamp(20px,6vw,72px);background:#fff;border-bottom:1px solid #dce4ef}.docs-head a{color:#1557c0;font-weight:750;text-decoration:none}.docs-main{max-width:880px;margin:auto;padding:48px 24px 70px}.docs-content{padding:34px;background:#fff;border:1px solid #dce4ef;border-radius:10px;box-shadow:0 8px 24px rgba(15,35,68,.05)}.docs-content h1{margin:0 0 24px;color:#062a5b;font-size:36px}.docs-content h2{margin:34px 0 12px;color:#073776;font-size:24px}.docs-content h3{margin:26px 0 10px;font-size:19px}.docs-content p,.docs-content li{color:#43546c;line-height:1.7}.docs-content ul{margin:12px 0 18px;padding-left:23px}.docs-content a{color:#1557c0;font-weight:700}@media(max-width:600px){.docs-main{padding:28px 14px}.docs-content{padding:24px 20px}.docs-content h1{font-size:30px}}</style></head>
<body><header class="docs-head"><a href="/">Abhipraya</a><nav><a href="/docs/README.md">Documentation</a> · <a href="/admin/login">Administrator sign in</a></nav></header><main class="docs-main"><article class="docs-content"><?= docsRender($markdown) ?></article></main></body></html>
