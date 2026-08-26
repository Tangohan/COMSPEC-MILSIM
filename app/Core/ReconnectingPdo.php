<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * PDO qui rouvre la session MySQL une fois si elle a été coupée (idle / wait_timeout).
 * Reconnexion in-place : les dépôts qui gardent cette instance restent valides.
 */
final class ReconnectingPdo extends PDO
{
    private string $dsn;

    private string $username;

    private string $password;

    /** @var array<int|string, mixed> */
    private array $options;

    private bool $retrying = false;

    /**
     * @param array<int|string, mixed> $options
     */
    public function __construct(string $dsn, string $username, string $password, array $options = [])
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        parent::__construct($dsn, $username, $password, $options);
        $this->applySessionSettings();
    }

    public function reconnect(): void
    {
        parent::__construct($this->dsn, $this->username, $this->password, $this->options);
        $this->applySessionSettings();
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return $this->retryOnLostConnection(fn () => parent::prepare($query, $options));
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return $this->retryOnLostConnection(function () use ($query, $fetchMode, $fetchModeArgs) {
            if ($fetchMode === null && $fetchModeArgs === []) {
                return parent::query($query);
            }

            return parent::query($query, $fetchMode, ...$fetchModeArgs);
        });
    }

    public function exec(string $statement): int|false
    {
        return $this->retryOnLostConnection(fn () => parent::exec($statement));
    }

    public function beginTransaction(): bool
    {
        return $this->retryOnLostConnection(fn () => parent::beginTransaction());
    }

    private function applySessionSettings(): void
    {
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND') && isset($this->options[PDO::MYSQL_ATTR_INIT_COMMAND])) {
            return;
        }
        parent::exec("SET time_zone = '+00:00'");
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function retryOnLostConnection(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (PDOException $e) {
            if ($this->retrying || $this->isInActiveTransaction() || !Database::isLostConnection($e)) {
                throw $e;
            }
            $this->retrying = true;
            try {
                $this->reconnect();

                return $operation();
            } finally {
                $this->retrying = false;
            }
        }
    }

    private function isInActiveTransaction(): bool
    {
        try {
            return $this->inTransaction();
        } catch (Throwable) {
            return false;
        }
    }
}
