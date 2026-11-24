<?php

declare(strict_types=1);

namespace ContactForm\Security;

use ContactForm\Support\Clock;
use PDO;

final readonly class RateLimiter
{
    public function __construct(
        private PDO $database,
        private Clock $clock,
        private string $hmacKey,
    ) {
    }

    public static function clientNetwork(string $address): string
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $address;
        }
        $packed = (string) inet_pton($address);
        if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return bin2hex(substr($packed, 0, 8)) . '::/64';
    }

    public function allow(string $scope, string $identifier, int $limit, int $windowSeconds): bool
    {
        $now = $this->clock->now();
        $expiredBefore = $now - $windowSeconds;
        $statement = $this->database->prepare(
            'INSERT INTO rate_limit_buckets (bucket_key, hits, window_started_at) VALUES (:bucket, 1, :now)
             ON CONFLICT (bucket_key) DO UPDATE SET
                 hits = CASE WHEN window_started_at <= :expired THEN 1 ELSE hits + 1 END,
                 window_started_at = CASE WHEN window_started_at <= :expired THEN excluded.window_started_at ELSE window_started_at END
             RETURNING hits',
        );
        $statement->execute([
            'bucket' => hash_hmac('sha256', $scope . "\0" . $identifier, $this->hmacKey),
            'now' => $now,
            'expired' => $expiredBefore,
        ]);
        $hits = (int) $statement->fetchColumn();
        $statement->closeCursor();

        return $hits <= $limit;
    }

    public function purgeOlderThan(int $timestamp): int
    {
        $statement = $this->database->prepare('DELETE FROM rate_limit_buckets WHERE window_started_at < :timestamp');
        $statement->execute(['timestamp' => $timestamp]);

        return $statement->rowCount();
    }
}
