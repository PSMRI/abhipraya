# Digital Public Good readiness

Abhipraya has a documentation foundation for open-source and Digital Public Good review. This is a **self-assessment**, not Digital Public Goods Alliance recognition. Only the Digital Public Goods Alliance can determine whether a submitted core solution qualifies for the DPG Registry.

## Current position

The current indicator-by-indicator readiness and evidence owners are maintained in the [DPG evidence register](compliance/dpg_evidence_register.md). The [open-source and DPG release status](compliance/open_source_dpg_release_status.md) records the release baseline. Current technical evidence is recorded in the [passed-test evidence register](testing/test_evidence_register.md), [VAPT test report](testing/vapt_test_report.md), and [performance test results](testing/performance_test_results.md).

Before nomination or a public release, complete the canonical evidence for SDG relevance, licensing, ownership, platform independence, documentation, non-PII extraction, privacy, open standards, security, and content safeguards. Deployment-specific legal, privacy, accessibility, and security reviews remain the responsibility of the deploying organisation.

## Documentation baseline check

Run from the project root:

```text
php tools/dpg_readiness_check.php
```

The command verifies that the canonical evidence files exist. It does not certify DPG status or replace formal legal, security, accessibility, performance, impact, or independent VAPT review.
