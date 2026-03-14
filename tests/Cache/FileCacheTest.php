<?php

declare (strict_types = 1);

namespace viavario\ecadclient\tests\Cache;

use DateInterval;
use PHPUnit\Framework\TestCase;
use viavario\ecadclient\Cache\FileCache;
use viavario\ecadclient\Cache\FileCacheInvalidArgumentException;

/**
 * Test suite for the file-based PSR-16 cache implementation.
 */
class FileCacheTest extends TestCase
{
    /**
     * Directory path used by each test run.
     */
    private string $cacheDir;

    /**
     * Cache instance under test.
     */
    private FileCache $cache;

    /**
     * Creates an isolated cache directory and cache instance for each test.
     */
    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'filecache_test_' . uniqid();
        $this->cache    = new FileCache($this->cacheDir, 3600);
    }

    /**
     * Removes test cache files and the temporary test directory.
     */
    protected function tearDown(): void
    {
        // Clean up all cache files and the test directory.
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->cacheDir);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * Verifies the constructor creates a missing cache directory.
     */
    public function testConstructorCreatesDirectoryWhenItDoesNotExist(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'filecache_new_' . uniqid();
        $this->assertDirectoryDoesNotExist($dir);

        new FileCache($dir);

        $this->assertDirectoryExists($dir);

        // Clean up
        @rmdir($dir);
    }

    /**
     * Verifies the constructor works when no directory is explicitly provided.
     */
    public function testConstructorUsesSystemTempDirWhenNoDirProvided(): void
    {
        $cache = new FileCache();
        $this->assertInstanceOf(FileCache::class, $cache);
    }

    /**
     * Verifies the constructor throws when the cache directory cannot be created.
     */
    public function testConstructorThrowsWhenDirectoryCannotBeCreated(): void
    {
        // Use a path that is impossible to create (file used as parent).
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'filecache_blockfile_' . uniqid();
        file_put_contents($file, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unable to create cache directory/');

        try {
            new FileCache($file . DIRECTORY_SEPARATOR . 'subdir');
        } finally {
            @unlink($file);
        }
    }

    // -------------------------------------------------------------------------
    // set / get
    // -------------------------------------------------------------------------

    /**
     * Verifies a stored value can be retrieved unchanged.
     */
    public function testSetAndGetReturnsSameValue(): void
    {
        $this->cache->set('foo', 'bar');
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    /**
     * Verifies get() returns the provided default for missing keys.
     */
    public function testGetReturnsDefaultWhenKeyDoesNotExist(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
        $this->assertSame('default', $this->cache->get('nonexistent', 'default'));
    }

    /**
     * Verifies setting an existing key overwrites its previous value.
     */
    public function testSetOverwritesExistingValue(): void
    {
        $this->cache->set('key', 'first');
        $this->cache->set('key', 'second');
        $this->assertSame('second', $this->cache->get('key'));
    }

    /**
     * Verifies scalar, array, boolean, and null values can be cached.
     */
    public function testSetAcceptsVariousValueTypes(): void
    {
        $this->cache->set('int', 42);
        $this->cache->set('float', 3.14);
        $this->cache->set('array', ['a' => 1]);
        $this->cache->set('bool', true);
        $this->cache->set('null', null);

        $this->assertSame(42, $this->cache->get('int'));
        $this->assertSame(3.14, $this->cache->get('float'));
        $this->assertSame(['a' => 1], $this->cache->get('array'));
        $this->assertSame(true, $this->cache->get('bool'));
        $this->assertNull($this->cache->get('null'));
    }

    /**
     * Verifies an entry with zero TTL expires immediately.
     */
    public function testSetWithZeroTtlExpiresImmediately(): void
    {
        $this->cache->set('expire', 'value', 0);
        $this->assertNull($this->cache->get('expire'));
    }

    /**
     * Verifies an entry with negative TTL expires immediately.
     */
    public function testSetWithNegativeTtlExpiresImmediately(): void
    {
        $this->cache->set('expire', 'value', -10);
        $this->assertNull($this->cache->get('expire'));
    }

    /**
     * Verifies DateInterval TTL values are supported.
     */
    public function testSetWithDateIntervalTtl(): void
    {
        $this->cache->set('interval', 'hello', new DateInterval('PT1H'));
        $this->assertSame('hello', $this->cache->get('interval'));
    }

    /**
     * Verifies null TTL falls back to the cache default TTL.
     */
    public function testSetWithNullTtlUsesDefaultTtl(): void
    {
        $cache = new FileCache($this->cacheDir, 3600);
        $cache->set('key', 'value', null);
        $this->assertSame('value', $cache->get('key'));
    }

    /**
     * Verifies null TTL with zero default TTL creates a non-expiring entry.
     */
    public function testSetWithNullTtlAndZeroDefaultTtlNeverExpires(): void
    {
        $cache = new FileCache($this->cacheDir, 0);
        $cache->set('key', 'immortal', null);
        $this->assertSame('immortal', $cache->get('key'));
    }

    // -------------------------------------------------------------------------
    // has
    // -------------------------------------------------------------------------

    /**
     * Verifies has() returns true for an existing, non-expired key.
     */
    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set('present', 'yes');
        $this->assertTrue($this->cache->has('present'));
    }

    /**
     * Verifies has() returns false for a key that does not exist.
     */
    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->has('ghost'));
    }

    /**
     * Verifies has() returns false for an expired key.
     */
    public function testHasReturnsFalseForExpiredKey(): void
    {
        $this->cache->set('expired', 'bye', 0);
        $this->assertFalse($this->cache->has('expired'));
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    /**
     * Verifies delete() removes an existing key.
     */
    public function testDeleteRemovesExistingKey(): void
    {
        $this->cache->set('remove', 'me');
        $this->assertTrue($this->cache->delete('remove'));
        $this->assertFalse($this->cache->has('remove'));
    }

    /**
     * Verifies delete() returns true for a key that does not exist.
     */
    public function testDeleteReturnsTrueForNonExistentKey(): void
    {
        $this->assertTrue($this->cache->delete('nope'));
    }

    // -------------------------------------------------------------------------
    // clear
    // -------------------------------------------------------------------------

    /**
     * Verifies clear() removes all stored entries.
     */
    public function testClearRemovesAllEntries(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->cache->set('c', 3);

        $result = $this->cache->clear();

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
        $this->assertFalse($this->cache->has('c'));
    }

    /**
     * Verifies clear() returns true when the cache is already empty.
     */
    public function testClearOnEmptyCacheReturnsTrue(): void
    {
        $this->assertTrue($this->cache->clear());
    }

    // -------------------------------------------------------------------------
    // getMultiple / setMultiple / deleteMultiple
    // -------------------------------------------------------------------------

    /**
     * Verifies setMultiple() and getMultiple() work together for multiple keys.
     */
    public function testSetMultipleAndGetMultiple(): void
    {
        $values = ['x' => 10, 'y' => 20, 'z' => 30];
        $this->cache->setMultiple($values);

        $result = $this->cache->getMultiple(['x', 'y', 'z']);

        $this->assertSame(['x' => 10, 'y' => 20, 'z' => 30], $result);
    }

    /**
     * Verifies getMultiple() returns the default for missing keys.
     */
    public function testGetMultipleReturnsDefaultForMissingKeys(): void
    {
        $result = $this->cache->getMultiple(['missing1', 'missing2'], 'fallback');
        $this->assertSame(['missing1' => 'fallback', 'missing2' => 'fallback'], $result);
    }

    /**
     * Verifies deleteMultiple() removes only the specified keys.
     */
    public function testDeleteMultipleRemovesAllSpecifiedKeys(): void
    {
        $this->cache->setMultiple(['p' => 1, 'q' => 2, 'r' => 3]);
        $result = $this->cache->deleteMultiple(['p', 'q']);

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('p'));
        $this->assertFalse($this->cache->has('q'));
        $this->assertTrue($this->cache->has('r'));
    }

    /**
     * Verifies setMultiple() throws when the input is not iterable.
     */
    public function testSetMultipleThrowsOnNonIterable(): void
    {
        $this->expectException(FileCacheInvalidArgumentException::class);
        $this->cache->setMultiple('not-an-array'); // @phpstan-ignore-line
    }

    /**
     * Verifies getMultiple() throws when the key list is not iterable.
     */
    public function testGetMultipleThrowsOnNonIterable(): void
    {
        $this->expectException(FileCacheInvalidArgumentException::class);
        $this->cache->getMultiple('not-an-array'); // @phpstan-ignore-line
    }

    /**
     * Verifies deleteMultiple() throws when the key list is not iterable.
     */
    public function testDeleteMultipleThrowsOnNonIterable(): void
    {
        $this->expectException(FileCacheInvalidArgumentException::class);
        $this->cache->deleteMultiple('not-an-array'); // @phpstan-ignore-line
    }

    // -------------------------------------------------------------------------
    // Key validation
    // -------------------------------------------------------------------------

    /**
     * Verifies get() throws for keys that violate PSR-16 key requirements.
     *
     * @dataProvider invalidKeyProvider
     * @param mixed $key
     */
    public function testGetThrowsOnInvalidKey($key): void
    {
        $this->expectException(FileCacheInvalidArgumentException::class);
        $this->cache->get($key);
    }

    /**
     * Verifies set() throws for keys that violate PSR-16 key requirements.
     *
     * @dataProvider invalidKeyProvider
     * @param mixed $key
     */
    public function testSetThrowsOnInvalidKey($key): void
    {
        $this->expectException(FileCacheInvalidArgumentException::class);
        $this->cache->set($key, 'value');
    }

    /**
     * Verifies has() throws for keys that violate PSR-16 key requirements.
     *
     * @dataProvider invalidKeyProvider
     * @param mixed $key
     */
    public function testHasThrowsOnInvalidKey($key): void
    {
        $this->expectException(FileCacheInvalidArgumentException::class);
        $this->cache->has($key);
    }

    /**
     * Verifies delete() throws for keys that violate PSR-16 key requirements.
     *
     * @dataProvider invalidKeyProvider
     * @param mixed $key
     */
    public function testDeleteThrowsOnInvalidKey($key): void
    {
        $this->expectException(FileCacheInvalidArgumentException::class);
        $this->cache->delete($key);
    }

    /**
     * Provides invalid cache keys for validation-related tests.
     *
     * @return array<string, array{0:mixed}>
     */
    public function invalidKeyProvider(): array
    {
        return [
            'empty string'       => [''],
            'non-string integer' => [42],
            'curly brace open'   => ['key{name'],
            'curly brace close'  => ['key}name'],
            'parenthesis open'   => ['key(name'],
            'parenthesis close'  => ['key)name'],
            'forward slash'      => ['key/name'],
            'backslash'          => ['key\\name'],
            'at sign'            => ['key@name'],
            'colon'              => ['key:name'],
        ];
    }

    // -------------------------------------------------------------------------
    // Corrupt cache file handling
    // -------------------------------------------------------------------------

    /**
     * Verifies corrupt cache content causes get() to return the default value.
     */
    public function testGetReturnsDefaultForCorruptCacheFile(): void
    {
        $this->cache->set('corrupt', 'original');

        // Overwrite the cache file with garbage.
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . sha1('corrupt') . '.cache';
        file_put_contents($file, 'not-valid-serialized-data!!!');

        $this->assertSame('fallback', $this->cache->get('corrupt', 'fallback'));
    }

    /**
     * Verifies corrupt cache content causes has() to return false.
     */
    public function testHasReturnsFalseForCorruptCacheFile(): void
    {
        $this->cache->set('corrupt2', 'original');

        $file = $this->cacheDir . DIRECTORY_SEPARATOR . sha1('corrupt2') . '.cache';
        file_put_contents($file, 'garbage');

        $this->assertFalse($this->cache->has('corrupt2'));
    }
}
