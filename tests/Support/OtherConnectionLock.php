<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Services\NamedLock;

/**
 * Holds a NamedLock from a second database connection, as a concurrent
 * request would.
 */
class OtherConnectionLock
{
    /** @var mysqli */
    private $connection;

    public function __construct()
    {
        $host = DB_HOST;
        $port = null;
        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
        }

        $this->connection = new mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port !== null ? (int) $port : null);
    }

    public function acquire(string $purpose, WC_Order $order, string $key): bool
    {
        return $this->query('SELECT GET_LOCK(?, 0)', self::name($purpose, $order, $key)) === 1;
    }

    public function release(string $purpose, WC_Order $order, string $key): void
    {
        $this->query('SELECT RELEASE_LOCK(?)', self::name($purpose, $order, $key));
    }

    /** Whether no connection holds the lock. */
    public function isFree(string $purpose, WC_Order $order, string $key): bool
    {
        return $this->query('SELECT IS_FREE_LOCK(?)', self::name($purpose, $order, $key)) === 1;
    }

    public function close(): void
    {
        $this->connection->close();
    }

    private function query(string $sql, string $name): ?int
    {
        $statement = $this->connection->prepare($sql);
        $statement->bind_param('s', $name);
        $statement->execute();
        $statement->bind_result($value);
        $statement->fetch();
        $statement->close();

        return $value === null ? null : (int) $value;
    }

    private static function name(string $purpose, WC_Order $order, string $key): string
    {
        $method = new ReflectionMethod(NamedLock::class, 'name');
        $method->setAccessible(true);

        return $method->invoke(null, $purpose, $order, $key);
    }
}
