# Performance test results

## Scope

This report records development-environment performance checks. It is not a production capacity benchmark because no approved service-level objective, representative infrastructure, production-sized dataset, or production concurrency profile was available.

## Result summary

| Test | Workload | Result | Status |
| --- | --- | --- | --- |
| Rendered documentation baseline | 20 sequential requests to the Test Results page | 100% HTTP 200; 64 ms minimum, 411 ms maximum, 137.95 ms average. | Pass (development baseline) |
| Documentation load smoke | 100 Test Results page requests at concurrency 10 | 100% HTTP 200; zero errors; 613.35 ms average, 3990 ms p95, 5512 ms maximum. | Completed; no approved target |
| Public-boundary API load smoke | 100 public-boundary API requests at concurrency 10 | 100% HTTP 200; zero errors; 441.32 ms average, 3408 ms p95, 8492 ms maximum. | Completed; no approved target |

## Test cases

| ID | Endpoint / page | Method | Requests | Concurrency | Success criteria | Observed result |
| --- | --- | --- | ---: | ---: | --- | --- |
| PERF-01 | Rendered Test Results page | Sequential GET | 20 | 1 | All requests return HTTP 200; record baseline timings. | All HTTP 200; 137.95 ms average. |
| PERF-02 | Rendered Test Results page | Read-only GET | 100 | 10 | No request error; collect average/p95/maximum. | 100% HTTP 200; zero errors; 613.35/3990/5512 ms average/p95/max. |
| PERF-03 | Public boundary API | Read-only GET | 100 | 10 | No request error; collect average/p95/maximum. | 100% HTTP 200; zero errors; 441.32/3408/8492 ms average/p95/max. |

## Interpretation

The baseline confirms that the tested pages and public boundary endpoint remained available under the recorded development workload. The p95 and maximum timings are not release targets and must not be used to claim production capacity or user-experience compliance.

## Required before production approval

1. Approve response-time, throughput, error-rate, database, and resource-utilisation targets.
2. Prepare a production-like UAT environment with representative data volume, TLS, caching, PHP/IIS settings, MySQL, Memurai, and network topology.
3. Run controlled read-only load, stress, endurance, and recovery tests with monitoring for CPU, memory, disk, database connections, slow queries, Redis/Memurai, and Kafka consumer/producer health where enabled.
4. Record request distribution, latency percentiles, errors, saturation point, recovery time, and remediation decisions.
5. Obtain technical and business approval against the agreed targets.

## Related records

- [Test results](test_results.md)
- [Test plan](test_plan.md)
- [Passed-test evidence register](test_evidence_register.md)
