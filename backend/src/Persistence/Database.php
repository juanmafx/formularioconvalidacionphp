<?php

declare(strict_types=1);

namespace ContactForm\Persistence;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(string $path): PDO
    {
        if ($path !== ':memory:') {
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Database directory could not be created.');
            }
            if (!is_file($path)) {
                $previousMask = umask(0077);
                $created = touch($path);
                umask($previousMask);
                if (!$created) {
                    throw new RuntimeException('Database file could not be created.');
                }
            }
        }
        $database = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $database->exec('PRAGMA foreign_keys = ON');
        $database->exec('PRAGMA busy_timeout = 5000');
        if ($path !== ':memory:') {
            $database->exec('PRAGMA journal_mode = WAL');
            $database->exec('PRAGMA synchronous = NORMAL');
            $database->exec('PRAGMA secure_delete = ON');
        }

        return $database;
    }

    public static function migrate(PDO $database, string $schemaPath): void
    {
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('Schema file could not be read.');
        }
        $database->exec($schema);
    }
}
