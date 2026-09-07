<?php
/**
 * Schedule Enterprise — CacheManager
 * ★ Unified file-based cache layer (Phase 3)
 */

namespace Schedule\Cache;

class CacheManager
{
    private string $dir;
    private int    $defaultTtl;

    public function __construct(string $dir = CACHE_DIR, int $defaultTtl = CACHE_DURATION)
    {
        $this->dir        = rtrim($dir, '/') . '/';
        $this->defaultTtl = $defaultTtl;
        $this->ensureDir();
    }

    // ── Public API ────────────────────────────────────────────

    public function get(string $key): mixed
    {
        $file = $this->path($key);
        if (!file_exists($file)) return null;

        $raw = @file_get_contents($file);
        if ($raw === false) return null;

        $data = @unserialize($raw);
        if ($data === false) { @unlink($file); return null; }

        if (time() > $data['expires']) { @unlink($file); return null; }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $file = $this->path($key);
        $payload = serialize([
            'key'     => $key,
            'value'   => $value,
            'created' => time(),
            'expires' => time() + ($ttl ?? $this->defaultTtl),
        ]);
        $ok = file_put_contents($file, $payload, LOCK_EX) !== false;
        if ($ok) @chmod($file, 0666);
        return $ok;
    }

    public function delete(string $key): bool
    {
        $file = $this->path($key);
        return file_exists($file) ? @unlink($file) : true;
    }

    public function flush(): int
    {
        return $this->removeFiles('*');
    }

    public function flushExpired(): int
    {
        $count = 0;
        foreach (glob($this->dir . '*.cache') ?: [] as $file) {
            $raw  = @file_get_contents($file);
            $data = $raw ? @unserialize($raw) : false;
            if ($data === false || time() > $data['expires']) {
                @unlink($file) && $count++;
            }
        }
        clearstatcache(true);
        return $count;
    }

    public function stats(): array
    {
        $files  = glob($this->dir . '*.cache') ?: [];
        $items  = [];
        $total  = 0;
        $valid  = 0;
        $now    = time();

        foreach ($files as $file) {
            $raw  = @file_get_contents($file);
            $data = $raw ? @unserialize($raw) : null;
            if (!$data) continue;
            $expired = $now > $data['expires'];
            if (!$expired) $valid++;
            $total++;
            $items[] = [
                'key'       => $data['key'] ?? basename($file),
                'size'      => filesize($file),
                'created'   => date('Y-m-d H:i:s', $data['created'] ?? 0),
                'age'       => $now - ($data['created'] ?? 0),
                'remaining' => max(0, $data['expires'] - $now),
                'expired'   => $expired,
            ];
        }
        return compact('total', 'valid', 'items');
    }

    // ── Helpers ───────────────────────────────────────────────

    private function path(string $key): string
    {
        return $this->dir . md5($key) . '.cache';
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }

    private function removeFiles(string $pattern): int
    {
        $count = 0;
        foreach (glob($this->dir . $pattern . '.cache') ?: [] as $file) {
            @unlink($file) && $count++;
        }
        clearstatcache(true);
        return $count;
    }

    // ── Static shorthand (backward compat helpers) ────────────

    public static function instance(): static
    {
        static $inst = null;
        return $inst ??= new static();
    }
}