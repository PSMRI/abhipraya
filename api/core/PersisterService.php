<?php
declare(strict_types=1);

/**
 * Shared persistence boundary for related database writes.
 *
 * The callback either commits as one unit or every change is rolled back.
 * Modules and repositories continue to own their prepared SQL and validation.
 */
final class PersisterService
{
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public static function transaction(mysqli $connection, callable $operation): mixed
    {
        $connection->begin_transaction();

        try {
            $result = $operation();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            try {
                $connection->rollback();
            } catch (Throwable) {
                // Preserve the original failure if the database is unavailable.
            }

            throw $exception;
        }
    }
}
