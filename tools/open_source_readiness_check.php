<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'License' => 'LICENSE',
    'Security policy' => 'SECURITY.md',
    'Contribution guide' => 'CONTRIBUTING.md',
    'Open-source status' => 'docs/open-source-dpg.md',
    'Governance' => 'docs/governance.md',
    'Open standards' => 'docs/open-standards.md',
    'Readiness checklist' => 'docs/compliance/open_source_readiness_checklist.md',
];
$failed = 0;
foreach ($checks as $label => $relative) {
    $ok = is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    printf("[%s] %s (%s)\n", $ok ? 'PASS' : 'FAIL', $label, $relative);
    $failed += $ok ? 0 : 1;
}
echo $failed ? "Open-source readiness check failed: {$failed} item(s) missing.\n" : "Open-source documentation baseline passed.\n";
exit($failed ? 1 : 0);
