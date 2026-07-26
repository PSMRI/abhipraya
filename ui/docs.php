<?php
declare(strict_types=1);

$documents = [
    'README' => 'README.md',
    'SUMMARY' => 'SUMMARY.md',
    'project-overview' => 'architecture/project_overview.md',
    'technical-architecture' => 'architecture/technical_architecture.md',
    'use-cases' => 'architecture/use_cases.md',
    'service-map' => 'architecture/service_map.md',
    'configuration-formats' => 'architecture/configuration_formats.md',
    'coding-standards' => 'architecture/coding_standards.md',
    'user-guide' => 'user/user_guide.md',
    'survey-version-publishing' => 'survey-version-publishing.md',
    'survey-question-types' => 'survey-question-types-reference.md',
    'developer-guide' => 'developer-guide.md',
    'data-dictionary' => 'database/data_dictionary_erd.md',
    'database-migration' => 'database/database_setup_and_migration.md',
    'api-reference' => 'api/README.md',
    'endpoint-inventory' => 'api/endpoint_inventory.md',
    'security' => 'security.md',
    'deployment' => 'deployment/deployment_guide.md',
    'backup-restore' => 'deployment/backup_restore_guide.md',
    'troubleshooting' => 'deployment/troubleshooting_faq.md',
    'test-plan' => 'testing/test_plan.md',
    'accessibility' => 'testing/wcag_web_platform_compliance.md',
    'privacy' => 'compliance/privacy_data_protection.md',
    'governance' => 'compliance/governance_and_ownership.md',
    'open-source-dpg' => 'compliance/open_source_dpg_release_status.md',
    'dpg-evidence' => 'compliance/dpg_evidence_register.md',
    'sdg-mapping' => 'compliance/sdg_mapping.md',
    'non-pii-data' => 'compliance/non_pii_data_export_import.md',
    'open-standards' => 'compliance/open_standards_mapping.md',
    'release-checklist' => 'compliance/release_checklist.md',
    'legal-privacy' => 'compliance/legal_privacy_confirmation.md',
    'licence-consistency' => 'compliance/license_consistency.md',
    'public-data-audit' => 'compliance/public_data_audit.md',
    'data-privacy-policy' => 'compliance/data_privacy_policy.md',
    'open-source-checklist' => 'compliance/open_source_readiness_checklist.md',
    'open_source_readiness_checklist' => 'compliance/open_source_readiness_checklist.md',
    'license_consistency' => 'compliance/license_consistency.md',
    'public_data_audit' => 'compliance/public_data_audit.md',
    'data_privacy_policy' => 'compliance/data_privacy_policy.md',
    'gitbook' => 'gitbook.md',
    'dpg-readiness' => 'dpg-readiness.md',
];
$key = (string) ($_GET['document'] ?? 'README');
if (!isset($documents[$key])) {
    http_response_code(404);
    exit('Document not found.');
}
$source = dirname(__DIR__) . '/docs/' . $documents[$key];
$markdown = (string) file_get_contents($source);

/* Keep the public documentation sidebar in step with the DPG evidence pack.
 * The page template is intentionally compact; this server-side output hook adds
 * the evidence links without adding a client-side dependency. */
ob_start(static function (string $html) use ($key): string {
    $dpgLinks = [
        'dpg-evidence' => 'DPG evidence register',
        'sdg-mapping' => 'SDG mapping',
        'non-pii-data' => 'Non-PII export and import',
        'open-standards' => 'Open standards',
        'release-checklist' => 'Release checklist',
        'legal-privacy' => 'Legal and privacy template',
        'open-source-checklist' => 'Open-source checklist',
        'licence-consistency' => 'Licence and attribution',
        'public-data-audit' => 'Public data audit',
        'data-privacy-policy' => 'Data privacy policy',
    ];
    $links = '';
    foreach ($dpgLinks as $slug => $label) {
        $class = $key === $slug ? ' class="is-active"' : '';
        $links .= '<a' . $class . ' href="/docs/' . $slug . '.md">' . $label . '</a>';
    }

    $html = preg_replace(
        '#(<a class="[^"]*" href="/docs/dpg-readiness\.md">DPG readiness</a>)(</details>)#',
        '$1' . $links . '$2',
        $html,
        1
    ) ?? $html;

    if (isset($dpgLinks[$key])) {
        $html = str_replace(
            '<details class="docs-nav-group"><summary>Governance and DPG</summary>',
            '<details class="docs-nav-group" open><summary>Governance and DPG</summary>',
            $html
        );
    }

    $html = preg_replace(
        '#(<a class="[^"]*" href="/docs/gitbook\.md">Publishing guide</a>)(</details>)#',
        '$1<a href="/LICENSE">Licence (GPL-3.0)</a><a href="/NOTICE">Notice</a><a href="/THIRD_PARTY_NOTICES.md">Third-party notices</a><a href="/CODE_OF_CONDUCT.md">Code of Conduct</a><a href="/MAINTAINERS.md">Maintainers</a>$2',
        $html,
        1
    ) ?? $html;

    return $html;
});

function docsInline(string $value): string
{
    $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $value = preg_replace_callback('/\[([^\]]+)\]\(([^\)]+)\)/', static function (array $match): string {
        if (preg_match('#^https?://#i', $match[2])) {
            return '<a href="' . $match[2] . '" rel="noopener noreferrer" target="_blank">' . $match[1] . '</a>';
        }
        $rootDocuments = [
            '../LICENSE' => '/LICENSE',
            '../NOTICE' => '/NOTICE',
            '../THIRD_PARTY_NOTICES.md' => '/THIRD_PARTY_NOTICES.md',
            '../CODE_OF_CONDUCT.md' => '/CODE_OF_CONDUCT.md',
            '../MAINTAINERS.md' => '/MAINTAINERS.md',
            '../../CONTRIBUTING.md' => '/CONTRIBUTING.md',
            '../../SECURITY.md' => '/SECURITY.md',
            '../../LICENSE' => '/LICENSE',
            '../../NOTICE' => '/NOTICE',
            '../../THIRD_PARTY_NOTICES.md' => '/THIRD_PARTY_NOTICES.md',
            '../../CODE_OF_CONDUCT.md' => '/CODE_OF_CONDUCT.md',
            '../../MAINTAINERS.md' => '/MAINTAINERS.md',
        ];
        if (isset($rootDocuments[$match[2]])) {
            return '<a href="' . $rootDocuments[$match[2]] . '">' . $match[1] . '</a>';
        }
        $target = preg_replace('/\.md$/', '', $match[2]);
        $routes = [
            'architecture/project_overview' => 'project-overview',
            'architecture/technical_architecture' => 'technical-architecture',
            'architecture/use_cases' => 'use-cases',
            'architecture/service_map' => 'service-map',
            'architecture/configuration_formats' => 'configuration-formats',
            'architecture/coding_standards' => 'coding-standards',
            'user/user_guide' => 'user-guide',
            '../survey-version-publishing' => 'survey-version-publishing',
            '../survey-question-types-reference' => 'survey-question-types',
            'survey-version-publishing' => 'survey-version-publishing',
            'survey-question-types-reference' => 'survey-question-types',
            'database/data_dictionary_erd' => 'data-dictionary',
            'database/database_setup_and_migration' => 'database-migration',
            'api/README' => 'api-reference',
            'api/endpoint_inventory' => 'endpoint-inventory',
            'deployment/deployment_guide' => 'deployment',
            'deployment/backup_restore_guide' => 'backup-restore',
            'deployment/troubleshooting_faq' => 'troubleshooting',
            'testing/test_plan' => 'test-plan',
            'testing/wcag_web_platform_compliance' => 'accessibility',
            'compliance/privacy_data_protection' => 'privacy',
            'compliance/governance_and_ownership' => 'governance',
            'compliance/open_source_dpg_release_status' => 'open-source-dpg',
            'compliance/dpg_evidence_register' => 'dpg-evidence',
            'compliance/sdg_mapping' => 'sdg-mapping',
            'compliance/non_pii_data_export_import' => 'non-pii-data',
            'compliance/open_standards_mapping' => 'open-standards',
            'compliance/release_checklist' => 'release-checklist',
            'compliance/legal_privacy_confirmation' => 'legal-privacy',
            'dpg_evidence_register' => 'dpg-evidence',
            'sdg_mapping' => 'sdg-mapping',
            'non_pii_data_export_import' => 'non-pii-data',
            'open_standards_mapping' => 'open-standards',
            'release_checklist' => 'release-checklist',
            'legal_privacy_confirmation' => 'legal-privacy',
            'license_consistency' => 'licence-consistency',
            'public_data_audit' => 'public-data-audit',
            'data_privacy_policy' => 'data-privacy-policy',
            'open_source_readiness_checklist' => 'open-source-checklist',
            'privacy_data_protection' => 'privacy',
            'governance_and_ownership' => 'governance',
            'open_source_dpg_release_status' => 'open-source-dpg',
            '../testing/wcag_web_platform_compliance' => 'accessibility',
            '../api/README' => 'api-reference',
            '../architecture/configuration_formats' => 'configuration-formats',
            '../architecture/technical_architecture' => 'technical-architecture',
            '../security' => 'security',
            '../testing/test_plan' => 'test-plan',
            'gitbook' => 'gitbook',
        ];
        $target = $routes[$target] ?? basename((string) $target);
        return '<a href="/docs/' . rawurlencode($target) . '.md">' . $match[1] . '</a>';
    }, $value) ?? $value;
    return preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $value) ?? $value;
}

function docsRender(string $markdown): string
{
    $output = '';
    $inList = false;
    $inCode = false;
    $tableRows = [];
    $flushTable = static function () use (&$output, &$tableRows): void {
        if ($tableRows === []) {
            return;
        }
        $output .= '<div class="docs-table-wrap"><table><thead><tr>';
        foreach ($tableRows[0] as $cell) {
            $output .= '<th>' . docsInline($cell) . '</th>';
        }
        $output .= '</tr></thead>';
        if (count($tableRows) > 1) {
            $output .= '<tbody>';
            foreach (array_slice($tableRows, 1) as $row) {
                $output .= '<tr>';
                foreach ($row as $cell) {
                    $output .= '<td>' . docsInline($cell) . '</td>';
                }
                $output .= '</tr>';
            }
            $output .= '</tbody>';
        }
        $output .= '</table></div>';
        $tableRows = [];
    };
    foreach (preg_split('/\R/', $markdown) as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '```')) {
            $flushTable();
            if ($inList) { $output .= '</ul>'; $inList = false; }
            $output .= $inCode ? '</code></pre>' : '<pre><code>';
            $inCode = !$inCode;
            continue;
        }
        if ($inCode) {
            $output .= htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
            continue;
        }
        if (preg_match('/^!\[([^\]]*)\]\((\/[^\)]+)\)$/', $trimmed, $image)) {
            $output .= '<figure class="docs-figure"><img src="' . htmlspecialchars($image[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . htmlspecialchars($image[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><figcaption>' . htmlspecialchars($image[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</figcaption></figure>';
            continue;
        }
        if (str_starts_with($trimmed, '|')) {
            $cells = array_values(array_filter(array_map('trim', explode('|', trim($trimmed, '|'))), static fn(string $cell): bool => $cell !== ''));
            $isSeparator = $cells !== [] && array_reduce($cells, static fn(bool $all, string $cell): bool => $all && (bool) preg_match('/^:?-{3,}:?$/', $cell), true);
            if (!$isSeparator) {
                $tableRows[] = $cells;
            }
            continue;
        }
        $flushTable();
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
        if ($inList) { $output .= '</ul>'; $inList = false; }
        $output .= '<p>' . docsInline($trimmed) . '</p>';
    }
    $flushTable();
    if ($inCode) { $output .= '</code></pre>'; }
    return $output . ($inList ? '</ul>' : '');
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= htmlspecialchars($key) ?> | Abhipraya documentation</title>
<link rel="stylesheet" href="/ui/assets/css/docs.css"><link rel="stylesheet" href="/ui/assets/css/reset.css"><link rel="stylesheet" href="/ui/assets/css/variables.css"><link rel="stylesheet" href="/ui/assets/css/global.css"><link rel="stylesheet" href="/ui/assets/css/app-shell.css">
 </head>
<body><header class="docs-head"><a href="/">Abhipraya</a><nav><a href="/admin/login">Administrator sign in</a></nav></header><div class="docs-layout"><aside class="docs-sidebar" aria-label="Documentation index"><p class="docs-sidebar-title">Start here</p><a class="<?= $key === 'README' ? 'is-active' : '' ?>" href="/docs/README.md">Documentation home</a><a class="<?= $key === 'project-overview' ? 'is-active' : '' ?>" href="/docs/project-overview.md">Abhipraya overview</a><a class="<?= $key === 'user-guide' ? 'is-active' : '' ?>" href="/docs/user-guide.md">User guide</a><details class="docs-nav-group"<?= in_array($key, ['technical-architecture', 'use-cases', 'service-map', 'configuration-formats', 'survey-version-publishing', 'survey-question-types'], true) ? ' open' : '' ?>><summary>Architecture</summary><a class="<?= $key === 'technical-architecture' ? 'is-active' : '' ?>" href="/docs/technical-architecture.md">Technical architecture</a><a class="<?= $key === 'use-cases' ? 'is-active' : '' ?>" href="/docs/use-cases.md">Use cases</a><a class="<?= $key === 'service-map' ? 'is-active' : '' ?>" href="/docs/service-map.md">Service map</a><a class="<?= $key === 'configuration-formats' ? 'is-active' : '' ?>" href="/docs/configuration-formats.md">JSON configuration</a><a class="<?= $key === 'survey-version-publishing' ? 'is-active' : '' ?>" href="/docs/survey-version-publishing.md">Survey versioning</a><a class="<?= $key === 'survey-question-types' ? 'is-active' : '' ?>" href="/docs/survey-question-types.md">Question types</a></details><details class="docs-nav-group"<?= in_array($key, ['developer-guide', 'api-reference', 'endpoint-inventory', 'data-dictionary', 'database-migration', 'coding-standards'], true) ? ' open' : '' ?>><summary>Development</summary><a class="<?= $key === 'developer-guide' ? 'is-active' : '' ?>" href="/docs/developer-guide.md">Developer guide</a><a class="<?= $key === 'api-reference' ? 'is-active' : '' ?>" href="/docs/api-reference.md">API guide</a><a class="<?= $key === 'endpoint-inventory' ? 'is-active' : '' ?>" href="/docs/endpoint-inventory.md">Endpoint inventory</a><a class="<?= $key === 'data-dictionary' ? 'is-active' : '' ?>" href="/docs/data-dictionary.md">Data dictionary</a><a class="<?= $key === 'database-migration' ? 'is-active' : '' ?>" href="/docs/database-migration.md">Database migration</a><a class="<?= $key === 'coding-standards' ? 'is-active' : '' ?>" href="/docs/coding-standards.md">Coding standards</a></details><details class="docs-nav-group"<?= in_array($key, ['deployment', 'backup-restore', 'troubleshooting', 'security', 'test-plan', 'accessibility'], true) ? ' open' : '' ?>><summary>Operations and testing</summary><a class="<?= $key === 'deployment' ? 'is-active' : '' ?>" href="/docs/deployment.md">Deployment</a><a class="<?= $key === 'backup-restore' ? 'is-active' : '' ?>" href="/docs/backup-restore.md">Backup and restore</a><a class="<?= $key === 'troubleshooting' ? 'is-active' : '' ?>" href="/docs/troubleshooting.md">Troubleshooting</a><a class="<?= $key === 'security' ? 'is-active' : '' ?>" href="/docs/security.md">Security</a><a class="<?= $key === 'test-plan' ? 'is-active' : '' ?>" href="/docs/test-plan.md">Test plan</a><a class="<?= $key === 'accessibility' ? 'is-active' : '' ?>" href="/docs/accessibility.md">Accessibility</a></details><details class="docs-nav-group"<?= in_array($key, ['privacy', 'governance', 'open-source-dpg', 'dpg-readiness'], true) ? ' open' : '' ?>><summary>Governance and DPG</summary><a class="<?= $key === 'privacy' ? 'is-active' : '' ?>" href="/docs/privacy.md">Privacy</a><a class="<?= $key === 'governance' ? 'is-active' : '' ?>" href="/docs/governance.md">Governance</a><a class="<?= $key === 'open-source-dpg' ? 'is-active' : '' ?>" href="/docs/open-source-dpg.md">Open source and DPG</a><a class="<?= $key === 'dpg-readiness' ? 'is-active' : '' ?>" href="/docs/dpg-readiness.md">DPG readiness</a></details><details class="docs-nav-group"><summary>Resources</summary><a href="/CONTRIBUTING.md">Contributing</a><a href="/SECURITY.md">Report a vulnerability</a><a class="<?= $key === 'gitbook' ? 'is-active' : '' ?>" href="/docs/gitbook.md">Publishing guide</a></details></aside><main class="docs-main"><article class="docs-content"><?= docsRender($markdown) ?></article></main></div></body></html>
