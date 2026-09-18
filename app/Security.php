<?php
declare(strict_types=1);

final class Security
{
    public static function enforceProduction(array $config): void
    {
        if ($config['environment'] !== 'production') return;
        $patched = (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 && PHP_RELEASE_VERSION >= 25)
            || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 5 && PHP_RELEASE_VERSION >= 10);
        if (!$patched) {
            throw new HttpError(503, 'Runtime produksi perlu diperbarui. Hubungi pengelola.');
        }
        if ($config['database']['user'] === 'root' || $config['database']['password'] === '') {
            throw new HttpError(503, 'Konfigurasi produksi belum siap. Hubungi pengelola.');
        }
        // The web server/trusted proxy must set HTTPS. Never trust client-supplied
        // X-Forwarded-Proto without a verified proxy configuration.
        if (PHP_SAPI !== 'cli' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
            throw new HttpError(400, 'Gunakan koneksi HTTPS.');
        }
    }

    public static function logException(Throwable $exception): void
    {
        error_log(json_encode([
            'request_id' => bin2hex(random_bytes(8)),
            'type' => get_class($exception),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
        ], JSON_THROW_ON_ERROR));
    }
}
