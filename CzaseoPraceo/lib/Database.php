<?php
/**
 * Połączenie z bazą (PDO, MySQL). Pojedyncza instancja na żądanie.
 */
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = (string) cfg('db.host', 'localhost');
        $name    = (string) cfg('db.name', '');
        $user    = (string) cfg('db.user', '');
        $pass    = (string) cfg('db.password', '');
        $charset = (string) cfg('db.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    /** Skrót: zapytanie z parametrami → tablica wierszy. */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Skrót: pojedynczy wiersz lub null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** Skrót: wykonanie INSERT/UPDATE/DELETE → liczba wierszy. */
    public static function run(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }
}
