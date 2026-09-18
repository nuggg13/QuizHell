<?php
declare(strict_types=1);

final class Auth
{
    // Public dummy hash, never an account credential. Matches registration cost.
    private const DUMMY_HASH = '$2y$12$dLcuCH18/Y0UkiPJBKYzJuXszl7Td5Meloe.oicyXljU9irNjNFX.';
    public function __construct(private PDO $db) {}

    public function register(string $email, string $password): void
    {
        $email = mb_strtolower(Input::text($email, 190));
        $password = Input::password($password);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new HttpError(422, 'Masukkan alamat email yang valid (maksimal 190 karakter).');
        }
        if (strlen($password) < 8) {
            throw new HttpError(422, 'Password harus memiliki 8–72 byte karakter.');
        }
        try {
            $stmt = $this->db->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
            $stmt->execute([$email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new HttpError(422, 'Email sudah terdaftar. Silakan login.');
            }
            throw $exception;
        }
        $this->establish((int) $this->db->lastInsertId(), $email);
    }

    public function login(string $email, string $password): void
    {
        $email = mb_strtolower(Input::text($email, 190));
        $password = Input::password($password);
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);
        if (!$user || !$valid) {
            throw new HttpError(422, 'Email atau password tidak sesuai.');
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user['id'],
            ]);
        }
        $this->establish((int) $user['id'], $user['email']);
    }

    private function establish(int $id, string $email): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $id, 'email' => $email];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['auth_started_at'] = time();
        $_SESSION['auth_last_seen'] = time();
    }

    public static function expire(): void
    {
        if (!isset($_SESSION['user']['id'])) return;
        $now = time();
        $started = (int) ($_SESSION['auth_started_at'] ?? 0);
        $seen = (int) ($_SESSION['auth_last_seen'] ?? 0);
        if (!$started || !$seen || $now - $seen >= 1800 || $now - $started >= 28800) {
            self::logout();
            return;
        }
        $_SESSION['auth_last_seen'] = $now;
    }

    public static function requireUser(): int
    {
        self::expire();
        if (!isset($_SESSION['user']['id'])) {
            redirect('login');
        }
        return (int) $_SESSION['user']['id'];
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['auth_started_at'], $_SESSION['auth_last_seen']);
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
