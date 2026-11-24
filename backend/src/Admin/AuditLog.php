<?php

declare(strict_types=1);

namespace ContactForm\Admin;

use ContactForm\Support\Clock;
use PDO;

final readonly class AuditLog
{
    public function __construct(
        private PDO $database,
        private Clock $clock,
        private string $hmacKey,
    ) {
    }

    public function record(string $eventType, ?int $adminUserId, string $clientAddress, ?string $subjectId = null): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO audit_events (admin_user_id, event_type, subject_id, client_fingerprint, created_at)
             VALUES (:admin_user_id, :event_type, :subject_id, :fingerprint, :created_at)',
        );
        $statement->execute([
            'admin_user_id' => $adminUserId,
            'event_type' => $eventType,
            'subject_id' => $subjectId,
            'fingerprint' => hash_hmac('sha256', $clientAddress, $this->hmacKey),
            'created_at' => $this->clock->now(),
        ]);
    }

    public function purgeOlderThan(int $timestamp): int
    {
        $statement = $this->database->prepare('DELETE FROM audit_events WHERE created_at < :timestamp');
        $statement->execute(['timestamp' => $timestamp]);

        return $statement->rowCount();
    }
}
