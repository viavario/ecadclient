<?php

declare (strict_types = 1);

namespace viavario\ecadclient\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Simple file-based implementation of PSR-16 cache interface.
 */
final class FileCache implements CacheInterface
{
    private string $cacheDir;
    private int $defaultTtl;

    /**
     * @param string|null $cacheDir  Cache directory path or null to use system temp directory
     * @param int         $defaultTtl Default TTL in seconds for cached entries
     */
    public function __construct(?string $cacheDir = null, int $defaultTtl = 3600)
    {

        $baseDir          = $cacheDir ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'viavario-ecadclient-cache';
        $this->cacheDir   = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $this->defaultTtl = $defaultTtl;

        if (! is_dir($this->cacheDir) && ! @mkdir($concurrentDirectory = $this->cacheDir, 0775, true) && ! is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create cache directory: %s', $this->cacheDir));
        }

        if (! is_writable($this->cacheDir)) {
            throw new \RuntimeException(sprintf('Cache directory is not writable: %s', $this->cacheDir));
        }
    }

    /**
     * {@inheritdoc}
     */
        public function get($key, $default = null)
    {


        $this->assertValidKey($key);

        $file = $this->getFilePath($key);

        if (! is_file($file)) {
            return $default;
        }

        $payload = $this->readPayload($file);

        if ($payload === null) {
            return $default;
        }

        if ($this->isExpired($payload)) {
            @unlink($file);
            return $default;
        }

        return $payload['data'];
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null): bool
    {

        $this->assertValidKey($key);

        $file      = $this->getFilePath($key);
        $expiresAt = $this->normalizeTtl($ttl);

        $payload = [
            'expires_at' => $expiresAt,
            'data'       => $value,
        ];

        $encoded  = serialize($payload);
        $tempFile = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($tempFile, $encoded, LOCK_EX) === false) {
            @unlink($tempFile);
            return false;
        }

        if (! @rename($tempFile, $file)) {
            @unlink($tempFile);
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key): bool
    {

        $this->assertValidKey($key);

        $file = $this->getFilePath($key);

        if (! is_file($file)) {
            return true;
        }

        return @unlink($file);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {

        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');
        if ($files === false) {
            return false;
        }

        $success = true;

        foreach ($files as $file) {
            if (is_file($file) && ! @unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null): iterable
    {

        if (! is_iterable($keys)) {
            throw new FileCacheInvalidArgumentException('Keys must be iterable.');
        }

        $result = [];

        foreach ($keys as $key) {
            $this->assertValidKey($key);
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null): bool
    {

        if (! is_iterable($values)) {
            throw new FileCacheInvalidArgumentException('Values must be iterable.');
        }

        $success = true;

        foreach ($values as $key => $value) {
            $this->assertValidKey($key);
            if (! $this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys): bool
    {

        if (! is_iterable($keys)) {
            throw new FileCacheInvalidArgumentException('Keys must be iterable.');
        }

        $success = true;

        foreach ($keys as $key) {
            $this->assertValidKey($key);
            if (! $this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key): bool
    {

        $this->assertValidKey($key);

        $file = $this->getFilePath($key);

        if (! is_file($file)) {
            return false;
        }

        $payload = $this->readPayload($file);

        if ($payload === null) {
            return false;
        }

        if ($this->isExpired($payload)) {
            @unlink($file);
            return false;
        }

        return true;
    }

    /**
     * Returns the file path that backs the provided cache key.
     */
    private function getFilePath(string $key): string
    {

        return $this->cacheDir . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
    }

    /**
     * Reads and validates the payload stored under the cache file.
     */
    private function readPayload(string $file): ?array
    {

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        try {
            $payload = unserialize($contents, ['allowed_classes' => true]);
        } catch (\Throwable $throwable) {
            return null;
        }

        if (
            ! is_array($payload) ||
            ! array_key_exists('expires_at', $payload) ||
            ! array_key_exists('data', $payload)
        ) {
            return null;
        }

        return $payload;
    }

    /**
     * Determines whether the cached payload has expired.
     */
    private function isExpired(array $payload): bool
    {

        $expiresAt = $payload['expires_at'];

        return $expiresAt !== null && time() >= $expiresAt;
    }

        /**
     * @param DateInterval|int|null $ttl
     */
    private function normalizeTtl($ttl): ?int

    {

        if ($ttl === null) {
            return $this->defaultTtl > 0 ? time() + $this->defaultTtl : null;
        }

        if ($ttl instanceof DateInterval) {
            $now = new \DateTimeImmutable();
            return $now->add($ttl)->getTimestamp();
        }

        if ($ttl <= 0) {
            return time() - 1;
        }

        return time() + $ttl;
    }

    /**
     * Ensures a cache key conforms to PSR-16 naming requirements.
     */
    private function assertValidKey($key): void
    {

        if (! is_string($key) || $key === '') {
            throw new FileCacheInvalidArgumentException('Cache key must be a non-empty string.');
        }

        if (preg_match('/[{}()\/\\\\@:]/', $key) === 1) {
            throw new FileCacheInvalidArgumentException(
                'Cache key contains reserved characters {}()/\\@:.'
            );
        }
    }
}
