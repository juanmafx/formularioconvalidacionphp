<?php

declare(strict_types=1);

namespace ContactForm\Contact;

use ContactForm\Security\FieldCipher;
use ContactForm\Support\Clock;
use PDO;

final readonly class SubmissionRepository
{
    public function __construct(
        private PDO $database,
        private FieldCipher $cipher,
        private Clock $clock,
    ) {
    }

    public function store(ContactSubmission $submission): string
    {
        $id = bin2hex(random_bytes(16));
        $statement = $this->database->prepare(
            'INSERT INTO submissions (id, payload_encrypted, key_id, created_at) VALUES (:id, :payload, :key_id, :created_at)',
        );
        $statement->execute([
            'id' => $id,
            'payload' => $this->cipher->encrypt(json_encode($submission->toArray(), JSON_THROW_ON_ERROR), self::context($id)),
            'key_id' => $this->cipher->activeKeyId(),
            'created_at' => $this->clock->now(),
        ]);

        return $id;
    }

    public function markNotification(string $id, string $status): void
    {
        $statement = $this->database->prepare('UPDATE submissions SET notification_status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    public function find(string $id): ?StoredSubmission
    {
        $statement = $this->database->prepare('SELECT * FROM submissions WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function page(int $page, int $perPage): array
    {
        $statement = $this->database->prepare('SELECT * FROM submissions ORDER BY created_at DESC, id LIMIT :limit OFFSET :offset');
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function count(): int
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM submissions');
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function purgeOlderThan(int $timestamp): int
    {
        $statement = $this->database->prepare('DELETE FROM submissions WHERE created_at < :timestamp');
        $statement->execute(['timestamp' => $timestamp]);

        return $statement->rowCount();
    }

    private function hydrate(array $row): StoredSubmission
    {
        $id = (string) $row['id'];
        $payload = json_decode(
            $this->cipher->decrypt((string) $row['payload_encrypted'], self::context($id)),
            true,
            4,
            JSON_THROW_ON_ERROR,
        );

        return new StoredSubmission(
            $id,
            ContactSubmission::fromArray(is_array($payload) ? $payload : []),
            (string) $row['notification_status'],
            (int) $row['created_at'],
        );
    }

    private static function context(string $id): string
    {
        return 'submission:' . $id;
    }
}
