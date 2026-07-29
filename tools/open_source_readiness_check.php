<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'License' => 'LICENSE',
    'Release notice' => 'NOTICE',
    'Third-party notices' => 'THIRD_PARTY_NOTICES.md',
    'Security policy' => 'SECURITY.md',
    'Contribution guide' => 'CONTRIBUTING.md',
    'Code of Conduct' => 'CODE_OF_CONDUCT.md',
    'Maintainer/contact template' => 'MAINTAINERS.md',
    'Open-source status' => 'docs/compliance/open_source_dpg_release_status.md',
    'Governance' => 'docs/compliance/governance_and_ownership.md',
    'Open standards' => 'docs/compliance/open_standards_mapping.md',
];
$failed = 0;
foreach ($checks as $label => $relative) {
    $ok = is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    printf("[%s] %s (%s)\n", $ok ? 'PASS' : 'FAIL', $label, $relative);
    $failed += $ok ? 0 : 1;
}
echo $failed ? "Open-source readiness check failed: {$failed} item(s) missing.\n" : "Open-source documentation baseline passed.\n";
exit($failed ? 1 : 0);
