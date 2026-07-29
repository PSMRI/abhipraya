<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Event Abstraction Layer
 * Event.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

class Event
{
    /** @var array<string, array<int, callable>> */
    private static array $listeners = [];
    private static bool $requestTraceStarted = false;
    private static float $requestStartedAt = 0.0;
    private static bool $kafkaUnavailableLogged = false;

    /**
     * Register a local listener for an event name.
     *
     * This keeps today's deployment simple while preserving a future path
     * to publish the same events to Kafka or another broker.
     */
    public static function listen(string $eventName, callable $listener): void
    {
        $eventName = trim($eventName);

        if ($eventName === '') {
            return;
        }

        self::$listeners[$eventName] ??= [];
        self::$listeners[$eventName][] = $listener;
    }

    /**
     * Dispatch an application event.
     *
     * Current implementation:
     * - appends JSON lines to api/storage/events/events-YYYY-MM-DD.log
     * - executes local PHP listeners registered with Event::listen()
     * - optionally publishes the same envelope to Kafka when configured
     */
    public static function dispatch(string $eventName, array $payload = [], array $meta = []): void
    {
        $eventName = trim($eventName);

        if ($eventName === '') {
            return;
        }

        $event = [
            'event' => $eventName,
            'payload' => $payload,
            'meta' => array_merge(self::defaultMeta(), $meta),
            'occurred_at' => date('c')
        ];

        self::writeLog($event);
        self::publishKafka($event);

        foreach (self::$listeners[$eventName] ?? [] as $listener) {
            try {
                $listener($event);
            } catch (Throwable $e) {
                error_log('SaQshi event listener failed [' . $eventName . ']: ' . $e->getMessage());
            }
        }
    }

    /**
     * Optional Kafka transport. It is deliberately best-effort: an event
     * broker outage must not fail a completed public submission or API call.
     * Configure ABHIPRAYA_EVENT_DRIVER=kafka and install php-rdkafka to use it.
     */
    private static function publishKafka(array $event): void
    {
        $driver = strtolower((string) (class_exists('Env') ? Env::get('ABHIPRAYA_EVENT_DRIVER', 'local') : 'local'));
        if ($driver !== 'kafka') {
            return;
        }

        if (!class_exists('RdKafka\\Producer') || !class_exists('RdKafka\\Conf')) {
            if (!self::$kafkaUnavailableLogged) {
                error_log('Abhipraya events: Kafka is configured but php-rdkafka is not installed; local event logging remains active.');
                self::$kafkaUnavailableLogged = true;
            }
            return;
        }

        $brokers = trim((string) Env::get('ABHIPRAYA_KAFKA_BROKERS', ''));
        if ($brokers === '') {
            error_log('Abhipraya events: Kafka is configured without ABHIPRAYA_KAFKA_BROKERS.');
            return;
        }

        try {
            $configuration = new RdKafka\Conf();
            $configuration->set('bootstrap.servers', $brokers);
            $configuration->set('acks', (string) Env::get('ABHIPRAYA_KAFKA_ACKS', 'all'));
            self::setKafkaOption($configuration, 'security.protocol', 'ABHIPRAYA_KAFKA_SECURITY_PROTOCOL');
            self::setKafkaOption($configuration, 'sasl.mechanisms', 'ABHIPRAYA_KAFKA_SASL_MECHANISMS');
            self::setKafkaOption($configuration, 'sasl.username', 'ABHIPRAYA_KAFKA_SASL_USERNAME');
            self::setKafkaOption($configuration, 'sasl.password', 'ABHIPRAYA_KAFKA_SASL_PASSWORD');

            $producer = new RdKafka\Producer($configuration);
            $prefix = trim((string) Env::get('ABHIPRAYA_KAFKA_TOPIC_PREFIX', 'abhipraya'), '.');
            $topic = $producer->newTopic(($prefix !== '' ? $prefix . '.' : '') . $event['event']);
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) ($event['meta']['request_id'] ?? ''));
            $producer->poll(0);
            $producer->flush(1000);
        } catch (Throwable $exception) {
            error_log('Abhipraya Kafka publish failed [' . $event['event'] . ']: ' . $exception->getMessage());
        }
    }

    private static function setKafkaOption(RdKafka\Conf $configuration, string $option, string $environmentKey): void
    {
        $value = Env::get($environmentKey, '');
        if ($value !== '') {
            $configuration->set($option, $value);
        }
    }

    /**
     * Enable automatic API lifecycle events for every endpoint that loads
     * bootstrap.php.
     *
     * Events:
     * - api.request.started
     * - api.request.finished
     */
    public static function traceRequest(): void
    {
        if (self::$requestTraceStarted) {
            return;
        }

        self::$requestTraceStarted = true;
        self::$requestStartedAt = microtime(true);

        self::dispatch('api.request.started', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'query' => $_SERVER['QUERY_STRING'] ?? null
        ]);

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            $durationMs = self::$requestStartedAt > 0
                ? round((microtime(true) - self::$requestStartedAt) * 1000, 2)
                : null;

            self::dispatch('api.request.finished', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'path' => $_SERVER['REQUEST_URI'] ?? null,
                'http_status' => http_response_code(),
                'duration_ms' => $durationMs,
                'fatal_error' => $error && in_array((int)$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
                    ? [
                        'type' => (int)$error['type'],
                        'message' => $error['message'] ?? '',
                        'file' => $error['file'] ?? '',
                        'line' => $error['line'] ?? 0
                    ]
                    : null
            ]);
        });
    }

    private static function defaultMeta(): array
    {
        return [
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8)),
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'user_id' => self::safeSessionValue('userId'),
            'facility_id' => self::safeSessionValue('facilityId')
        ];
    }

    private static function safeSessionValue(string $method): ?int
    {
        if (!class_exists('SessionManager') || !method_exists('SessionManager', $method)) {
            return null;
        }

        try {
            $value = SessionManager::$method();
            return is_numeric($value) ? (int)$value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function writeLog(array $event): void
    {
        $dir = dirname(__DIR__) . '/storage/events';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('SaQshi event log directory could not be created: ' . $dir);
            return;
        }

        $file = $dir . '/events-' . date('Y-m-d') . '.log';
        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('SaQshi event log write failed: ' . $file);
        }
    }
}
