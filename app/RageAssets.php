<?php
declare(strict_types=1);

final class RageAssets
{
    private const EXTENSIONS = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'],
        'sounds' => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'],
    ];

    public static function load(array $config, string $publicDirectory): array
    {
        $root = realpath($publicDirectory);
        $result = ['images' => [], 'sounds' => []];
        foreach (self::EXTENSIONS as $type => $extensions) {
            foreach ($config[$type] ?? [] as $category => $pool) {
                $paths = [];
                $directory = trim((string) ($pool['directory'] ?? ''), '/');
                $files = $pool['files'] ?? null;
                if ($files === null) {
                    // An absent/unreadable directory simply produces an empty pool.
                    $absolute = $root === false ? false : self::inside($root, $directory);
                    $files = $absolute && is_dir($absolute) ? (@scandir($absolute) ?: []) : [];
                }
                foreach (is_array($files) ? $files : [] as $file) {
                    if (is_string($file) && !str_contains($file, '/') && !str_contains($file, '\\') && !str_starts_with($file, '.')) {
                        $paths[] = $directory . '/' . $file;
                    }
                }
                $urls = $root === false ? [] : self::available($root, $paths, $extensions);
                if (!$urls && !empty($pool['fallback']) && is_array($pool['fallback'])) {
                    $urls = $root === false ? [] : self::available($root, $pool['fallback'], $extensions);
                }
                $result[$type][$category] = $urls;
            }
        }
        return $result;
    }

    private static function inside(string $root, string $path): string|false
    {
        if ($path === '' || str_contains($path, "\0")) return false;
        $absolute = realpath($root . DIRECTORY_SEPARATOR . $path);
        if ($absolute === false || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) return false;
        return $absolute;
    }

    private static function available(string $root, array $paths, array $extensions): array
    {
        $urls = [];
        foreach ($paths as $path) {
            if (!is_string($path) || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) continue;
            $absolute = self::inside($root, $path);
            if (!$absolute || !is_file($absolute) || !is_readable($absolute)) continue;
            // Encode filenames so spaces, #, quotes, and Unicode remain valid URLs.
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolute, strlen($root) + 1));
            $urls[] = implode('/', array_map('rawurlencode', explode('/', $relative)));
        }
        return array_values(array_unique($urls));
    }
}
