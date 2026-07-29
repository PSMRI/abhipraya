# Kafka and event-driven architecture

## Overview

Abhipraya supports an optional event-driven extension through the shared `Event` layer. The core application remains synchronous: it validates a request, commits transactional data, and returns JSON. When Kafka is enabled, the same domain event is published for independent consumers such as notifications, analytics pipelines, and approved external integrations.

```text
Public survey API → MySQL response commit → Event layer → Kafka topic → independent consumers
                                         ↘ local JSON event log
```

Kafka is not required. Local JSON-line event logs and in-process listeners remain the default.

## Current events

| Event | Trigger | Kafka topic | Data policy |
| --- | --- | --- | --- |
| `survey.response.submitted` | A validated response is stored | `abhipraya.survey.response.submitted` | Submission ID, service-location ID, service-area ID, survey code and version only. No answers, GPS, IP address, or device ID. |
| `api.request.started` | Versioned API request starts | `abhipraya.api.request.started` | Request lifecycle metadata. |
| `api.request.finished` | Versioned API request ends | `abhipraya.api.request.finished` | Status and duration metadata. |

Each event is a JSON envelope containing `event`, `payload`, `meta`, and `occurred_at`. Consumers must be idempotent and use `submission_id` or `meta.request_id` to handle duplicate delivery.

## Enable Kafka

Kafka publishing uses the PHP `php-rdkafka` extension. Install it in the active PHP runtime and set protected environment values:

```ini
ABHIPRAYA_EVENT_DRIVER=kafka
ABHIPRAYA_KAFKA_BROKERS=<private-broker-host>:9092
ABHIPRAYA_KAFKA_TOPIC_PREFIX=abhipraya
ABHIPRAYA_KAFKA_ACKS=all
```

Optional TLS/SASL settings use `ABHIPRAYA_KAFKA_SECURITY_PROTOCOL`, `ABHIPRAYA_KAFKA_SASL_MECHANISMS`, `ABHIPRAYA_KAFKA_SASL_USERNAME`, and `ABHIPRAYA_KAFKA_SASL_PASSWORD`. Keep all broker credentials outside source control and reload PHP after changes.

## Development environment configuration

### 1. Run a development Kafka-compatible broker

Start an organisation-approved development Kafka-compatible broker and create a non-production topic for Abhipraya events. Keep it isolated from UAT and production brokers. The development broker must be reachable from the PHP runtime using the configured host and port.

Create or allow the topic for the first domain event:

```text
abhipraya.survey.response.submitted
```

For the isolated Docker development broker used by this project, run:

```powershell
docker start abhipraya-kafka
docker ps --filter "name=abhipraya-kafka"
docker exec abhipraya-kafka /opt/kafka/bin/kafka-topics.sh --create --if-not-exists --topic abhipraya.survey.response.submitted --bootstrap-server localhost:9092 --partitions 1 --replication-factor 1
docker exec abhipraya-kafka /opt/kafka/bin/kafka-topics.sh --describe --topic abhipraya.survey.response.submitted --bootstrap-server localhost:9092
```

To create the broker when it does not yet exist, start Docker Desktop and run:

```powershell
docker pull apache/kafka:4.3.1
docker run -d --name abhipraya-kafka --restart unless-stopped -p 127.0.0.1:9092:9092 apache/kafka:4.3.1
```

### 2. Install the PHP producer extension

Install and enable `php-rdkafka` for the same PHP runtime that serves Abhipraya. Verify it after restarting PHP:

```powershell
php -m | findstr rdkafka
php --ri rdkafka
```

The command-line and web-server PHP installations can use different `php.ini` files. Verify the web-server runtime as well; a CLI-only extension installation is not enough.

If either command does not show `rdkafka`, install the DLL that matches the active PHP version, architecture and thread-safety mode, enable it in the PHP `php.ini` file, and recycle the IIS application pool. Do not enable an extension built for a different PHP build.

### 3. Configure the development environment

Add the following to the development-environment, uncommitted `.env` file:

```ini
ABHIPRAYA_EVENT_DRIVER=kafka
ABHIPRAYA_KAFKA_BROKERS=127.0.0.1:9092
ABHIPRAYA_KAFKA_TOPIC_PREFIX=abhipraya
ABHIPRAYA_KAFKA_ACKS=all
```

Reload PHP, submit a test survey, then inspect the topic using the broker's approved consumer tool. Confirm that the event contains `submission_id`, service-location/service-area identifiers, survey code, and version—but not response answers or sensitive telemetry.

Use this local consumer command to inspect one development event:

```powershell
docker exec abhipraya-kafka /opt/kafka/bin/kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic abhipraya.survey.response.submitted --from-beginning --max-messages 1 --timeout-ms 10000
```

To return to the default local behaviour, set `ABHIPRAYA_EVENT_DRIVER=local` and reload PHP. Local event logs remain available under `api/storage/events/`.

## Production configuration

### 1. Broker and topic controls

- Use private broker endpoints; never expose Kafka listeners to the public internet.
- Create an approved topic naming and retention policy, for example `abhipraya.survey.response.submitted`.
- Grant the Abhipraya producer identity write access only to its approved topics; it does not need consumer, topic-administration, or cluster-administration access.
- Use TLS and authenticated SASL settings when traffic crosses host or network boundaries.
- Monitor broker availability, producer failures, disk usage, replication health, and consumer lag.

### 2. Configure protected production secrets

Set the following through the deployment secret store or protected environment configuration on every PHP/web-server node:

```ini
ABHIPRAYA_EVENT_DRIVER=kafka
ABHIPRAYA_KAFKA_BROKERS=<private-broker-1>:9093,<private-broker-2>:9093
ABHIPRAYA_KAFKA_TOPIC_PREFIX=abhipraya
ABHIPRAYA_KAFKA_ACKS=all
ABHIPRAYA_KAFKA_SECURITY_PROTOCOL=SASL_SSL
ABHIPRAYA_KAFKA_SASL_MECHANISMS=SCRAM-SHA-512
ABHIPRAYA_KAFKA_SASL_USERNAME=<producer-identity>
ABHIPRAYA_KAFKA_SASL_PASSWORD=<secret-from-secret-store>
```

The exact security protocol and SASL mechanism must match the broker platform. Do not copy these example values unchanged if the infrastructure team uses a different approved configuration.

After deploying the protected settings, recycle the relevant IIS application pool on each application node and verify the extension before releasing traffic:

```powershell
php -m | findstr rdkafka
php --ri rdkafka
Import-Module WebAdministration
Restart-WebAppPool -Name "YOUR_APPLICATION_POOL_NAME"
```

Use the broker platform's approved administration tool to create the topic and grant the producer identity write-only access. Do not use the single-node Docker commands above in production.

### 3. Release verification

1. Confirm `php-rdkafka` is enabled on every PHP/web-server node.
2. Confirm each node can reach only the approved broker endpoints.
3. Submit a non-production test response through one node and verify one correctly shaped event on the topic.
4. Verify the API response still succeeds when the broker is temporarily unavailable; review the application error log and local event log.
5. Verify the producer identity cannot read sensitive topics or administer the cluster.
6. Record the topic, producer identity owner, retention period, monitoring owner, and rollback owner in deployment evidence.

### 4. Production rollback

Set `ABHIPRAYA_EVENT_DRIVER=local` on all application nodes and reload PHP. This stops Kafka publishing but keeps local logs and the core API operational. Existing Kafka consumers should tolerate the absence of new events. Do not delete topics or consumer offsets as part of an application rollback.

## Delivery and security

The current publisher is best-effort: it logs locally first and does not fail a successful API request if Kafka is unavailable. This protects public feedback submission from broker outages, but does not guarantee delivery. Add a MySQL transactional outbox and separately operated relay before using Kafka for legally, financially, or operationally critical actions.

Consumers must use separate consumer groups, validate event data, apply least-privilege topic access, and monitor consumer lag and failures. Never publish survey answers, precise location, IP addresses, device IDs, passwords, or session values without an approved data-protection design.

## Related documentation

- [Service architecture and map](service_map.md)
- [Technology architecture and tools](technology_architecture.md)
- [Deployment architecture](deployment_architecture.md)
- [Deployment guide](../deployment/deployment_guide.md)
