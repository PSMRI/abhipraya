# SDG relevance and public-benefit evidence

Abhipraya supports **SDG 3: Good Health and Well-being** by helping healthcare facilities measure beneficiary experience, identify service gaps, and document corrective action. It also supports **SDG 10** through anonymous, multilingual and location-accessible participation.

## Measurable indicators

- response coverage: facilities with at least one response / configured facilities;
- beneficiary experience: average rating by indicator and facility;
- service equity: response and rating breakdown by age, gender, beneficiary type and geography;
- improvement cycle: low-scoring indicators with assigned and resolved CAPA actions;
- access: language usage, mobile completion rate and location-validation success rate.

All public reporting must use aggregated, non-identifying data. Publish methodology, reporting period, denominator and limitations with every result.

## Current implementation evidence

The current installation provides the following measurable evidence sources:

- **Facilities covered:** 428 facilities are defined in `api/masters/facilityCodes.json`; the dashboard separately reports facilities that have submitted feedback.
- **Beneficiaries reached:** the beneficiary-reach proxy is the number of submitted survey responses, shown as `Total responses` on the Overview dashboard. Do not describe this as unique people because the system does not collect beneficiary identity.
- **Service improvements:** CAPA records and resolved issue counts are the approved evidence source. A deployment must record the baseline, action date, owner and closure result for each improvement.
- **Before/after indicators:** compare the same indicator, facility scope, response denominator and reporting period before and after a CAPA action. The dashboard’s monthly trend is a volume trend, not proof of service improvement.
- **Geographic/equity impact:** use aggregated facility geography, beneficiary type, gender, age group and language breakdowns. Suppress small cells and never publish exact coordinates or identifiable combinations.
- **Reporting period and methodology:** every export must state `from`, `to`, facility/department filters, response count, indicator scale, excluded/invalid responses and calculation formula. The default Overview trend displays the most recent six months.

### Evidence limitation

The repository contains the measurement mechanism, but deployment-specific before/after improvement claims require an approved baseline and CAPA closure dataset. Those claims must not be invented from response volume alone.

## SDG category decision record

Based on the documented product scope and evidence model, Abhipraya is currently mapped to:

- **SDG 3 – Good Health and Well-being (primary):** healthcare-facility feedback, beneficiary experience measurement, service-gap identification, and CAPA follow-up.
- **SDG 10 – Reduced Inequalities (supporting):** anonymous participation, multilingual questionnaires, mobile access, and disaggregated equity analysis.

The implementation may also provide supporting evidence for **SDG 16** (accountability and responsive institutions) and **SDG 17** (open standards and partnerships), but these are candidate mappings and require deployment-specific governance or partnership evidence before being claimed as outcomes.

### How the mapping is reviewed

Run `php tools/dpg_readiness_check.php`. The tool scans this document for declared SDGs, measurable indicators, implementation evidence, non-identifying reporting, and limitations, then prints heuristic candidate mappings. The tool is a review aid; it does not certify SDG alignment. Each published claim must include the reporting period, denominator, methodology, baseline/after comparison where applicable, and aggregated non-PII evidence.
