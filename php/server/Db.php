<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * Accesso al database. In produzione è MySQL/MariaDB (cPanel); SQLite serve
 * solo per provare l'API in locale e nei test.
 */
final class Db
{
    public readonly string $driver;

    private function __construct(private PDO $pdo, string $driver)
    {
        $this->driver = $driver;
    }

    /** @param array<string,mixed> $cfg sezione "db" di config.php */
    public static function connect(array $cfg): self
    {
        $driver = $cfg['driver'] ?? 'mysql';
        if ($driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . ($cfg['path'] ?? ':memory:'));
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $cfg['host'] ?? 'localhost',
                $cfg['name'] ?? ''
            );
            $pdo = new PDO($dsn, (string)($cfg['user'] ?? ''), (string)($cfg['pass'] ?? ''));
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        if ($driver !== 'sqlite') {
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        }
        return new self($pdo, $driver);
    }

    /** @return array<int, array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function row(string $sql, array $params = []): ?array
    {
        $rows = $this->all($sql, $params);
        return $rows[0] ?? null;
    }

    public function exec(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public function lastId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    public function now(): string
    {
        return $this->driver === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    /** Nomi dei servizi di un appuntamento, in ordine di inserimento. */
    public function serviceNamesSql(string $aptAlias): string
    {
        $group = $this->driver === 'sqlite'
            ? "GROUP_CONCAT(sv.name, ', ')"
            : "GROUP_CONCAT(sv.name ORDER BY aps.id SEPARATOR ', ')";
        return "(SELECT $group FROM appointment_services aps JOIN services sv ON sv.id = aps.service_id WHERE aps.appointment_id = $aptAlias.id)";
    }

    /** Prossimo numero progressivo degli appuntamenti di un negozio, senza doppioni. */
    public function nextCounter(int $accountId): int
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->beginTransaction();
            $this->exec('UPDATE accounts SET appointment_counter = appointment_counter + 1 WHERE id = ?', [$accountId]);
            $next = (int)($this->row('SELECT appointment_counter AS n FROM accounts WHERE id = ?', [$accountId])['n'] ?? 1);
            $this->pdo->commit();
            return $next;
        }
        $this->exec('UPDATE accounts SET appointment_counter = LAST_INSERT_ID(appointment_counter + 1) WHERE id = ?', [$accountId]);
        return (int)$this->row('SELECT LAST_INSERT_ID() AS n')['n'];
    }

    public function metaGet(string $key): ?string
    {
        $r = $this->row('SELECT meta_value FROM _meta WHERE meta_key = ?', [$key]);
        return $r === null ? null : (string)$r['meta_value'];
    }

    public function metaSet(string $key, string $value): void
    {
        if ($this->metaGet($key) === null) {
            $this->exec('INSERT INTO _meta (meta_key, meta_value) VALUES (?, ?)', [$key, $value]);
        } else {
            $this->exec('UPDATE _meta SET meta_value = ? WHERE meta_key = ?', [$value, $key]);
        }
    }

    /** Un solo processo alla volta per nome (MySQL); con SQLite non serve. */
    public function lock(string $name, int $seconds = 5): bool
    {
        if ($this->driver === 'sqlite') {
            return true;
        }
        return (int)($this->row('SELECT GET_LOCK(?, ?) AS l', ['appuntamenti_' . $name, $seconds])['l'] ?? 0) === 1;
    }

    public function unlock(string $name): void
    {
        if ($this->driver !== 'sqlite') {
            $this->row('SELECT RELEASE_LOCK(?) AS l', ['appuntamenti_' . $name]);
        }
    }

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        if ($this->driver === 'sqlite') {
            foreach ($this->all("PRAGMA table_info($table)") as $c) {
                if ($c['name'] === $column) {
                    return true;
                }
            }
            return false;
        }
        return $this->row(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) !== null;
    }

    public function tableExists(string $table): bool
    {
        if ($this->driver === 'sqlite') {
            return $this->row("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]) !== null;
        }
        return $this->row(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) !== null;
    }
}
