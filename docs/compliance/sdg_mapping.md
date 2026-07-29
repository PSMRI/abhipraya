# SDG relevance and public-benefit evidence

This record describes Abhipraya's contribution to the Sustainable Development Goals and the supporting deployment evidence.

## Purpose

Abhipraya is an anonymous public-feedback platform. Its healthcare deployment gives beneficiaries a simple way to report their experience and gives authorised officials aggregated evidence to improve services.

## Intended public benefit statement

Abhipraya is intended to provide a configurable, privacy-conscious feedback and service-improvement tool that public-service organisations can adapt to their local context. It enables people to share service experience through accessible feedback channels and enables authorised teams to review aggregated, non-identifying trends and manage corrective actions. The software does not itself deliver health services, establish public-service outcomes, or guarantee impact; implementing organisations remain responsible for inclusive deployment, data protection, accessibility, governance, and measuring outcomes.

## Deployment evidence (aggregate, verified report extract)

The downloaded Bihar, India reports for **2 April 2025 to 29 July 2026** record **16,144 feedback responses** from **40 participating facilities** across **seven health-service departments**: Accident and Emergency, SNCU, Labour Room (LR), Maternity Ward, Maternity OT, IPD and OPD. The configured survey set contains **52 rating indicators**: seven indicators for each department except OPD, which has ten. The indicator report records **146,184 valid rating responses** in total.

These are aggregate report figures only. This documentation does not include respondent identities, free-text feedback, device identifiers, precise locations or facility-level response counts.

## Primary SDG contribution

**Main SDG:** SDG 3 — Good Health and Well-being.

**Supporting SDGs:** SDG 16 — Peace, Justice and Strong Institutions; and SDG 10 — Reduced Inequalities.

### Relevant SDG targets

| Role | Target | Relevance to Abhipraya | Evidence to retain |
| --- | --- | --- | --- |
| Main target | **3.8** — achieve universal health coverage, including access to quality essential health-care services | Aggregated feedback and CAPA workflows can help an implementing organisation identify service-quality gaps. The platform does not itself measure universal-health-coverage outcomes. | [Public-survey and CAPA workflow results](../testing/test_results.md); downloaded Bihar aggregate report extracts for 2 April 2025 to 29 July 2026 (16,144 responses, 40 participating facilities and seven departments); configured survey packages; aggregated, non-identifying service-quality trends; approved CAPA records and before/after indicators. |
| Supporting target | **16.6** — develop effective, accountable and transparent institutions | Aggregated reporting and controlled CAPA records can support accountable service-improvement processes. | [Analytics, report and CAPA workflow evidence](../testing/test_evidence_register.md); role/scope test evidence; redacted audit-log and CAPA-status samples. |
| Supporting target | **16.7** — ensure responsive, inclusive, participatory and representative decision-making | Anonymous, multilingual feedback can support participatory service feedback where it is deployed inclusively. | [Public-survey end-to-end evidence](../testing/test_evidence_register.md); published survey languages; non-identifying participation statistics; documented community feedback process. |
| Supporting target | **10.2** — promote social, economic and political inclusion of all | Mobile-first, multilingual and accessibility-focused feedback can help reduce barriers to participation; local accessibility and inclusion evidence remains required. | [WCAG and web-platform evidence](../testing/wcag_web_platform_compliance.md); [NVDA journey result](../testing/test_results.md); language configuration and approved usability-feedback summaries. |

### Evidence links for SDG-level contributions

| SDG | Supporting evidence to retain |
| --- | --- |
| SDG 3 — Good Health and Well-being | [Public-survey and CAPA workflow results](../testing/test_results.md); configured survey packages; aggregated, non-identifying service-quality reports; approved CAPA records and before/after indicators. |
| SDG 16 — Peace, Justice and Strong Institutions | [Role, scope, analytics and CAPA evidence](../testing/test_evidence_register.md); [privacy and data-protection guidance](privacy_data_protection.md); redacted audit-log and aggregated-report samples. |
| SDG 10 — Reduced Inequalities | [WCAG and web-platform evidence](../testing/wcag_web_platform_compliance.md); [NVDA screen-reader journey](../testing/test_results.md); language configuration and approved usability-feedback summaries. |

## Theory of change

```text
Anonymous feedback
        ↓
Aggregated indicator trends
        ↓
Officials identify low-performing services
        ↓
Corrective and preventive action (CAPA)
        ↓
Follow-up reporting and improved service experience
```

## Measurement approach

The platform itself does not prove health outcomes. It produces operational evidence that an implementing organisation can use to measure improvement:

1. Record the baseline for a configured indicator and time period.
2. Record the corrective action, responsible unit and target date.
3. Compare the same indicator over a later period using the same survey version.
4. Publish only aggregated, non-identifying results.
