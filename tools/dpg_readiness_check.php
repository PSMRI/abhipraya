<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'SDG mapping' => 'docs/compliance/sdg_mapping.md',
    'Privacy guide' => 'docs/compliance/privacy_data_protection.md',
    'Non-PII guide' => 'docs/compliance/non_pii_data_export_import.md',
    'Security guide' => 'docs/security.md',
    'Test plan' => 'docs/testing/test_plan.md',
    'DPG readiness' => 'docs/dpg-readiness.md',
];
$failed = 0;
foreach ($checks as $label => $relative) {
    $ok = is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    printf("[%s] %s (%s)\n", $ok ? 'PASS' : 'FAIL', $label, $relative);
    $failed += $ok ? 0 : 1;
}
/* Report the SDG categories explicitly so reviewers can see the intended
   alignment and the evidence boundary in one command. */
$sdgPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'compliance' . DIRECTORY_SEPARATOR . 'sdg_mapping.md';
$sdgText = is_file($sdgPath) ? (string) file_get_contents($sdgPath) : '';
preg_match_all('/SDG\s+([0-9]+)/i', $sdgText, $sdgMatches);
$sdgs = array_values(array_unique($sdgMatches[1] ?? []));
echo 'SDG categories: ' . ($sdgs ? implode(', ', array_map(static fn($id) => 'SDG ' . $id, $sdgs)) : 'not declared') . PHP_EOL;
/* A heuristic is useful for discovery, but the mapping still requires human
   review and measurable deployment evidence. */
$signals = [
    'SDG 3 (Good Health and Well-being)' => ['healthcare', 'health facility', 'beneficiary experience'],
    'SDG 10 (Reduced Inequalities)' => ['anonymous', 'multilingual', 'equity', 'gender', 'age group'],
    'SDG 16 (Peace, Justice and Strong Institutions)' => ['accountability', 'transparent', 'governance', 'grievance'],
    'SDG 17 (Partnerships for the Goals)' => ['open-source', 'open standards', 'interoperability'],
];
echo "Heuristic SDG candidates (review required):" . PHP_EOL;
foreach ($signals as $sdg => $terms) {
    $hits = array_values(array_filter($terms, static fn($term) => stripos($sdgText, $term) !== false));
    if ($hits) echo "- {$sdg}: signals=" . implode(', ', $hits) . PHP_EOL;
}
foreach ([
    'measurement approach' => 'Measurement approach',
    'limitations' => 'does not prove health outcomes',
    'non-identifying reporting' => 'non-identifying',
] as $label => $needle) {
    $present = stripos($sdgText, $needle) !== false;
    printf("[%s] SDG %s\n", $present ? 'PASS' : 'WARN', $label);
}
echo $failed ? "DPG readiness check failed: {$failed} item(s) missing.\n" : "DPG readiness documentation baseline passed.\n";
exit($failed ? 1 : 0);
