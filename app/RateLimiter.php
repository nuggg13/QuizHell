<?php
declare(strict_types=1);

final class RateLimiter
{
    public function __construct(private PDO $db) {}

    public function hit(string $scope, string $subject, int $limit, int $seconds): void
    {
        $now = time();
        $expires = (intdiv($now, $seconds) + 1) * $seconds;
        $bucket = hash('sha256', $scope . "\0" . $subject . "\0" . $expires);
        // Atomic increments work across PHP workers and sessions. A concurrent read
        // can reject early, but cannot admit requests beyond the bucket's limit.
        $stmt = $this->db->prepare('INSERT INTO rate_limits (bucket, hits, expires_at) VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE hits=LEAST(hits+1, 4294967295)');
        $stmt->execute([$bucket, $expires]);
        $stmt = $this->db->prepare('SELECT hits FROM rate_limits WHERE bucket=?');
        $stmt->execute([$bucket]);
        if ((int) $stmt->fetchColumn() > $limit) {
            header('Retry-After: ' . max(1, $expires - $now));
            throw new HttpError(429, 'Terlalu banyak percobaan. Coba lagi nanti.');
        }
        // Bounded housekeeping; no scheduler is required for a small deployment.
        if (random_int(1, 100) === 1) {
            $stmt = $this->db->prepare('DELETE FROM rate_limits WHERE expires_at < ? LIMIT 1000');
            $stmt->execute([$now]);
        }
    }
}
