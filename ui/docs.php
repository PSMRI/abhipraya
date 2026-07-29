<?php
declare(strict_types=1);

$documents = [
    'README' => 'README.md',
    'SUMMARY' => 'SUMMARY.md',
    'master_data_management' => 'architecture/master_data_management.md',
    'boundary' => 'api/boundary.md',
    'why-abhipraya' => 'architecture/why_abhipraya.md',
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
    'test_results' => 'testing/test_results.md',
    'dpg_open_source_test_evidence_matrix' => 'testing/dpg_open_source_test_matrix.md',
    'accessibility' => 'testing/wcag_web_platform_compliance.md',
    'privacy' => 'compliance/privacy_data_protection.md',
    'governance' => 'compliance/governance_and_ownership.md',
    'open-source-dpg' => 'compliance/open_source_dpg_release_status.md',
    'dpg-evidence' => 'compliance/dpg_evidence_register.md',
    'sdg-mapping' => 'sdg-mapping.md',
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
    'openapi-reference' => 'api/openapi.yaml',
    'openapi.yaml' => 'api/openapi.yaml',
    'postman-collection-reference' => 'api/postman_collection.json',
    'web.config' => '../web.config',
    'composer.json' => '../composer.json',
    'composer.lock' => '../composer.lock',
    'publish_survey_version.php' => '../tools/publish_survey_version.php',
];

/* GitBook navigation source of truth. Every page listed in SUMMARY.md is an
 * allowed documentation route and is shown in the HTML sidebar. */
$summaryPath = dirname(__DIR__) . '/docs/SUMMARY.md';
$summaryMarkdown = (string) file_get_contents($summaryPath);
preg_match_all('/^-\s+\[[^\]]+\]\(([^)#]+)\.md\)/m', $summaryMarkdown, $summaryLinks);
foreach ($summaryLinks[1] as $summaryTarget) {
    $summaryTarget = str_replace('\\', '/', trim($summaryTarget));
    if ($summaryTarget === '' || str_starts_with($summaryTarget, '../') || str_contains($summaryTarget, '..')) {
        continue;
    }
    $documents[$summaryTarget] = $summaryTarget . '.md';
}
$documents['openapi-reference'] = 'api/openapi.yaml';
$documents['postman-collection-reference'] = 'api/postman_collection.json';
$key = (string) ($_GET['document'] ?? 'README');
if (!isset($documents[$key])) {
    http_response_code(404);
    exit('Document not found.');
}
$source = dirname(__DIR__) . '/docs/' . $documents[$key];
$markdown = (string) file_get_contents($source);
$docsCurrentPath = $documents[$key];
$docsAllowedPaths = array_values($documents);
$renderedDocuments = [
    'openapi-reference' => ['OpenAPI 3.1 specification', '/docs/api/openapi.yaml', 'Download or open the raw YAML file'],
    'openapi.yaml' => ['OpenAPI 3.1 specification', '/docs/api/openapi.yaml', 'Download or open the raw YAML file'],
    'postman-collection-reference' => ['Postman collection', '/docs/api/postman_collection.json', 'Download or import the raw JSON collection'],
    'web.config' => ['IIS web configuration', '', ''],
    'composer.json' => ['Composer manifest', '', ''],
    'composer.lock' => ['Composer dependency lock file', '', ''],
    'publish_survey_version.php' => ['Survey publishing validation script', '', ''],
];
$contentHtml = isset($renderedDocuments[$key])
    ? '<h1>' . $renderedDocuments[$key][0] . '</h1><p>Rendered source for review.' . ($renderedDocuments[$key][1] !== '' ? ' <a href="' . $renderedDocuments[$key][1] . '">' . $renderedDocuments[$key][2] . '</a>.' : '') . '</p><pre><code>' . htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>'
    : docsRender($markdown);

/* Keep the public documentation sidebar in step with the DPG evidence pack.
 * The page template is intentionally compact; this server-side output hook adds
 * the evidence links without adding a client-side dependency. */
ob_start(static function (string $html) use ($key, $summaryMarkdown): string {
    $dpgLinks = [
        'dpg-evidence' => 'DPG evidence register',
        'sdg-mapping' => 'SDG relevance and public-benefit evidence',
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

    $html = str_replace(
        '<a class="' . ($key === 'project-overview' ? 'is-active' : '') . '" href="/docs/project-overview.md">Abhipraya overview</a>',
        '<a class="' . ($key === 'why-abhipraya' ? 'is-active' : '') . '" href="/docs/why-abhipraya.md">Why Abhipraya</a><a class="' . ($key === 'project-overview' ? 'is-active' : '') . '" href="/docs/project-overview.md">Abhipraya overview</a>',
        $html
    );
    $html = str_replace(
        '<header class="docs-head">',
        '<header class="docs-head"><button class="docs-menu-toggle" type="button" aria-label="Open documentation menu" aria-controls="docs-sidebar" aria-expanded="false"><span></span><span></span><span></span></button>',
        $html
    );
    $html = str_replace(
        '<div class="docs-layout">',
        '<div class="docs-menu-backdrop" data-docs-menu-close aria-hidden="true"></div><div class="docs-layout">',
        $html
    );
    $html = str_replace(
        '<aside class="docs-sidebar" aria-label="Documentation index">',
        '<aside id="docs-sidebar" class="docs-sidebar" aria-label="Documentation index"><button class="docs-menu-close" type="button" aria-label="Close documentation menu">×</button>',
        $html
    );
    $html = str_replace(
        '</body>',
        '<script src="/ui/assets/js/docs-mobile-nav.js?v=20260726-3" defer></script></body>',
        $html
    );
    $html = str_replace(
        '/ui/assets/css/docs.css',
        '/ui/assets/css/docs.css?v=20260728-4',
        $html
    );

    $sidebar = docsSidebar($summaryMarkdown, $key);
    $html = preg_replace('#<aside id="docs-sidebar" class="docs-sidebar" aria-label="Documentation index">.*?</aside>#s', $sidebar, $html, 1) ?? $html;

    return $html;
});

function docsInline(string $value): string
{
    global $docsAllowedPaths, $docsCurrentPath;

    $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $value = preg_replace_callback('/\[([^\]]+)\]\(([^\)]+)\)/', static function (array $match): string {
        global $docsAllowedPaths, $docsCurrentPath;

        if (preg_match('#^https?://#i', $match[2])) {
            return '<a href="' . $match[2] . '" rel="noopener noreferrer" target="_blank">' . $match[1] . '</a>';
        }
        $rootDocuments = [
            '../LICENSE' => '/docs/compliance/license.md',
            '../NOTICE' => '/docs/compliance/notice.md',
            '../THIRD_PARTY_NOTICES.md' => '/docs/compliance/third-party-notices.md',
            '../CODE_OF_CONDUCT.md' => '/docs/compliance/code-of-conduct.md',
            '../MAINTAINERS.md' => '/docs/compliance/maintainers.md',
            '../../CONTRIBUTING.md' => '/docs/compliance/contributing.md',
            '../../SECURITY.md' => '/docs/compliance/security-policy.md',
            '../../LICENSE' => '/docs/compliance/license.md',
            '../../NOTICE' => '/docs/compliance/notice.md',
            '../../THIRD_PARTY_NOTICES.md' => '/docs/compliance/third-party-notices.md',
            '../../CODE_OF_CONDUCT.md' => '/docs/compliance/code-of-conduct.md',
            '../../MAINTAINERS.md' => '/docs/compliance/maintainers.md',
            '../../api/database/schema/abhipraya_core_schema.sql' => '/api/database/schema/abhipraya_core_schema.sql',
            '../api/database/schema/abhipraya_core_schema.sql' => '/api/database/schema/abhipraya_core_schema.sql',
            '../../web.config' => '/docs/web.config.md',
            '../../composer.json' => '/docs/composer.json.md',
            '../../composer.lock' => '/docs/composer.lock.md',
            '../../tools/publish_survey_version.php' => '/docs/publish_survey_version.php.md',
            'openapi.yaml' => '/docs/api/openapi.yaml',
            '../api/openapi.yaml' => '/docs/openapi.yaml.md',
            'postman_collection.json' => '/docs/api/postman_collection.json',
            '../test_results.md' => '/docs/test_results.md',
            'test_evidence_register.md' => '/docs/testing/test_evidence_register.md',
            'vapt_test_report.md' => '/docs/testing/vapt_test_report.md',
            'performance_test_results.md' => '/docs/testing/performance_test_results.md',
            '../dpg_open_source_test_evidence_matrix.md' => '/docs/dpg_open_source_test_evidence_matrix.md',
        ];
        if (isset($rootDocuments[$match[2]])) {
            return '<a href="' . $rootDocuments[$match[2]] . '">' . $match[1] . '</a>';
        }
        [$linkPath, $fragment] = array_pad(explode('#', $match[2], 2), 2, '');

        $pathParts = [];
        foreach (array_merge(explode('/', dirname($docsCurrentPath)), explode('/', $linkPath)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($pathParts);
                continue;
            }
            $pathParts[] = $part;
        }
        $resolvedPath = implode('/', $pathParts);
        if (in_array($resolvedPath, $docsAllowedPaths, true)) {
            $href = '/docs/' . implode('/', array_map('rawurlencode', explode('/', $resolvedPath)));
            if ($fragment !== '') {
                $href .= '#' . rawurlencode($fragment);
            }
            return '<a href="' . $href . '">' . $match[1] . '</a>';
        }

        /* Application source/configuration references are evidence labels, not
         * public documentation routes. Do not render a link that would lead to
         * a 404 page or expose an executable PHP source path. */
        if (str_contains($linkPath, '/') || preg_match('/\.(?:php|json|yaml|yml|config|css)$/i', $linkPath)) {
            return '<span class="docs-source-reference" title="' . htmlspecialchars($linkPath, ENT_QUOTES, 'UTF-8') . '">' . $match[1] . '</span>';
        }
        $target = preg_replace('/\.md$/', '', $linkPath);
        $routes = [
            'architecture/why_abhipraya' => 'why-abhipraya',
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
            '../architecture/event_driven_architecture' => 'architecture/event_driven_architecture',
            '../security' => 'security',
            '../testing/test_plan' => 'test-plan',
            'gitbook' => 'gitbook',
        ];
        $target = $routes[$target] ?? basename((string) $target);
        $href = '/docs/' . rawurlencode($target) . '.md';
        if ($fragment !== '') {
            $href .= '#' . rawurlencode($fragment);
        }
        return '<a href="' . $href . '">' . $match[1] . '</a>';
    }, $value) ?? $value;
    $value = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $value) ?? $value;
    return preg_replace('/`([^`]+)`/', '<code>$1</code>', $value) ?? $value;
}

function docsSidebar(string $summaryMarkdown, string $key): string
{
    $html = '<aside id="docs-sidebar" class="docs-sidebar" aria-label="Documentation index"><button class="docs-menu-close" type="button" aria-label="Close documentation menu">×</button><p class="docs-sidebar-title">Documentation</p><a href="https://github.com/PSMRI/abhipraya" target="_blank" rel="noopener noreferrer">Source code on GitHub ↗</a>';
    $groupOpen = false;
    foreach (preg_split('/\R/', $summaryMarkdown) as $line) {
        if (preg_match('/^##\s+(.+)$/', trim($line), $heading)) {
            if ($groupOpen) {
                $html .= '</details>';
            }
            $html .= '<details class="docs-nav-group"><summary>' . htmlspecialchars($heading[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</summary>';
            $groupOpen = true;
            continue;
        }
        if (!preg_match('/^-\s+\[([^\]]+)\]\(([^)#]+)\.md(?:#[^)]+)?\)$/', trim($line), $link)) {
            continue;
        }
        $target = str_replace('\\', '/', trim($link[2]));
        if ($target === '' || str_starts_with($target, '../') || str_contains($target, '..')) {
            continue;
        }
        $isActive = $key === $target;
        if ($isActive) {
            /* A page load rebuilds the sidebar. Keep the active page's
             * SUMMARY.md section expanded instead of resetting it to '+'. */
            $detailsStart = '<details class="docs-nav-group">';
            $detailsPosition = strrpos($html, $detailsStart);
            if ($detailsPosition !== false) {
                $html = substr_replace(
                    $html,
                    '<details class="docs-nav-group" open>',
                    $detailsPosition,
                    strlen($detailsStart)
                );
            }
        }
        $active = $isActive ? ' class="is-active"' : '';
        $urlPath = implode('/', array_map('rawurlencode', explode('/', $target)));
        $html .= '<a' . $active . ' href="/docs/' . $urlPath . '.md">' . htmlspecialchars($link[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
    }
    if ($groupOpen) {
        $html .= '</details>';
    }
    return $html . '</aside>';
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
            $anchor = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $match[2]), '-'));
            $output .= '<h' . $level . ($anchor !== '' ? ' id="' . htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') . '"' : '') . '>' . docsInline($match[2]) . '</h' . $level . '>';
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
<body><header class="docs-head"><a href="/">Abhipraya</a><nav><a href="/admin/login">Administrator sign in</a></nav></header><div class="docs-layout"><aside class="docs-sidebar" aria-label="Documentation index"><p class="docs-sidebar-title">Start here</p><a class="<?= $key === 'README' ? 'is-active' : '' ?>" href="/docs/README.md">Documentation home</a></aside><main class="docs-main"><article class="docs-content"><?= $contentHtml ?></article></main></div></body></html>
