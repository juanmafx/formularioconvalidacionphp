<?php

declare(strict_types=1);

namespace ContactForm\Admin;

use ContactForm\Support\Clock;
use PDO;
use Throwable;

final readonly class AdminUserRepository
{
    public function __construct(
        private PDO $database,
        private Clock $clock,
    ) {
    }

    public function create(string $username, string $passwordHash, string $encryptedTotpSecret, array $recoveryCodeHashes): int
    {
        $now = $this->clock->now();
        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'INSERT INTO admin_users (username, password_hash, totp_secret_encrypted, created_at, updated_at)
                 VALUES (:username, :password_hash, :totp_secret, :now, :now)',
            );
            $statement->execute([
                'username' => $username,
                'password_hash' => $passwordHash,
                'totp_secret' => $encryptedTotpSecret,
                'now' => $now,
            ]);
            $id = (int) $this->database->lastInsertId();
            $insertCode = $this->database->prepare('INSERT INTO admin_recovery_codes (admin_user_id, code_hash) VALUES (:id, :hash)');
            foreach ($recoveryCodeHashes as $hash) {
                $insertCode->execute(['id' => $id, 'hash' => $hash]);
            }
            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }

        return $id;
    }

    public function findByUsername(string $username): ?AdminUser
    {
        $statement = $this->database->prepare('SELECT * FROM admin_users WHERE username = :username');
        $statement->execute(['username' => $username]);

        return $this->hydrate($statement->fetch());
    }

    public function findById(int $id): ?AdminUser
    {
        $statement = $this->database->prepare('SELECT * FROM admin_users WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $this->hydrate($statement->fetch());
    }

    public function registerFailure(int $id, int $maximumAttempts, int $lockSeconds): void
    {
        $now = $this->clock->now();
        $statement = $this->database->prepare(
            'UPDATE admin_users SET
                 locked_until = CASE WHEN failed_attempts + 1 >= :maximum THEN :locked_until ELSE locked_until END,
                 failed_attempts = CASE WHEN failed_attempts + 1 >= :maximum THEN 0 ELSE failed_attempts + 1 END,
                 updated_at = :now
             WHERE id = :id',
        );
        $statement->bindValue('maximum', $maximumAttempts, PDO::PARAM_INT);
        $statement->bindValue('locked_until', $now + $lockSeconds, PDO::PARAM_INT);
        $statement->bindValue('now', $now, PDO::PARAM_INT);
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function clearFailures(int $id): void
    {
        $statement = $this->database->prepare('UPDATE admin_users SET failed_attempts = 0, locked_until = 0, updated_at = :now WHERE id = :id');
        $statement->execute(['now' => $this->clock->now(), 'id' => $id]);
    }

    public function advanceTotpStep(int $id, int $step): bool
    {
        $statement = $this->database->prepare('UPDATE admin_users SET totp_last_step = :step, updated_at = :now WHERE id = :id AND totp_last_step < :step');
        $statement->execute(['step' => $step, 'now' => $this->clock->now(), 'id' => $id]);

        return $statement->rowCount() === 1;
    }

    public function consumeRecoveryCode(int $id, string $codeHash): bool
    {
        $statement = $this->database->prepare(
            'UPDATE admin_recovery_codes SET used_at = :now WHERE admin_user_id = :id AND code_hash = :hash AND used_at IS NULL',
        );
        $statement->execute(['now' => $this->clock->now(), 'id' => $id, 'hash' => $codeHash]);

        return $statement->rowCount() === 1;
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = $this->database->prepare('UPDATE admin_users SET password_hash = :hash, updated_at = :now WHERE id = :id');
        $statement->execute(['hash' => $passwordHash, 'now' => $this->clock->now(), 'id' => $id]);
    }

    private function hydrate(mixed $row): ?AdminUser
    {
        if (!is_array($row)) {
            return null;
        }

        return new AdminUser(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['password_hash'],
            (string) $row['totp_secret_encrypted'],
            (int) $row['totp_last_step'],
            (int) $row['locked_until'],
        );
    }
}
