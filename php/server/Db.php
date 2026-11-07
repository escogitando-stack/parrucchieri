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

    /** Prossimo numero progressivo degli appuntamenti, senza doppioni. */
    public function nextCounter(): int
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->beginTransaction();
            $cur = $this->row("SELECT meta_value FROM _meta WHERE meta_key = 'appointment_counter'");
            $next = (int)($cur['meta_value'] ?? 0) + 1;
            $this->exec(
                "INSERT INTO _meta (meta_key, meta_value) VALUES ('appointment_counter', ?)
                 ON CONFLICT(meta_key) DO UPDATE SET meta_value = excluded.meta_value",
                [(string)$next]
            );
            $this->pdo->commit();
            return $next;
        }
        $this->exec("INSERT IGNORE INTO _meta (meta_key, meta_value) VALUES ('appointment_counter', '0')");
        $this->exec("UPDATE _meta SET meta_value = LAST_INSERT_ID(CAST(meta_value AS UNSIGNED) + 1) WHERE meta_key = 'appointment_counter'");
        return (int)$this->row('SELECT LAST_INSERT_ID() AS n')['n'];
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
