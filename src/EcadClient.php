<?php

declare(strict_types=1);

namespace viavario\ecadclient;

use Psr\SimpleCache\CacheInterface;

/**
 * Client for searching healthcare professionals on webappsa.riziv-inami.fgov.be
 * by RIZIV registration number.
 */
class EcadClient
{
    private const BASE_URL    = 'https://apps.health.belgium.be/ecad-public-search-rs';
    private const SEARCH_PATH = '/professionals';
    private const PROFESSIONS_PATH = '/ref-data/professions';
    private const DISCIPLINES_PATH = '/ref-data/disciplines';
    private const TIMEOUT     = 15;

    /**
     * @var CacheInterface|null The cache instance. If null, caching is disabled.
     */
    private ?CacheInterface $cache;

    /** @var int The time-to-live (TTL) for cached data, in seconds. */
    private int $ttl;

    public function __construct(?CacheInterface $cache = null, int $ttl = 86400)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    /**
     * Fetches the list of professions from the eCad API and caches it, if a cache instance is available.
     *
     * @param  bool $refresh  Whether to refresh the cached professions list.
     *
     * @return  array<int, EcadProfession>  The list of professions.
     */
    public function getProfessions(bool $refresh = false): array
    {
        $cacheKey = 'viavario_ecadclient_professions';

        if ($this->cache && !$refresh && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $response = $this->get(self::BASE_URL . self::PROFESSIONS_PATH);
        $professions = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to decode JSON response: " . json_last_error_msg());
        }

        $result = [];
        foreach ($professions as $profession) {
            if (isset($profession['id'])) {
                $description = $profession['description'] ?? [];
                $result[(int) $profession['id']] = new EcadProfession(
                    $profession['id'],
                    $profession['categoryCd'] ?? '',
                    $profession['code'] ?? '',
                    [
                        'textFr' => $description['textFr'] ?? '',
                        'textNl' => $description['textNl'] ?? '',
                    ]
                );
            }
        }

        if ($this->cache) {
            $this->cache->set($cacheKey, $result, $this->ttl);
        }
        
        return $result;
    }

    /**
     * Fetches the list of disciplines from the eCad API and caches it, if a cache instance is available.
     *
     * @param   bool $refresh  Whether to refresh the cached disciplines list.
     *
     * @return  array<int, EcadDiscipline>  The list of disciplines.
     */
    public function getDisciplines(bool $refresh = false): array
    {
        $cacheKey = 'viavario_ecadclient_disciplines';

        if ($this->cache && !$refresh && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $response = $this->get(self::BASE_URL . self::DISCIPLINES_PATH);
        $disciplines = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to decode JSON response: " . json_last_error_msg());
        }

        $result = [];
        foreach ($disciplines as $discipline) {
            if (isset($discipline['id'])) {
                $description = $discipline['description'] ?? [];
                $result[(int) $discipline['id']] = new EcadDiscipline(
                    $discipline['id'],
                    $discipline['typeC'] ?? '',
                    $discipline['professionCategoryCd'] ?? '',
                    $discipline['businessTypeCode'] ?? '',
                    [
                        'textFr' => $description['textFr'] ?? '',
                        'textNl' => $description['textNl'] ?? '',
                    ]
                );
            }
        }

        if ($this->cache) {
            $this->cache->set($cacheKey, $result, $this->ttl);
        }

        return $result;
    }

    /**
     * Perform the POST request and parse results.
     *
     * @param  array<string, string> $params
     * @return EcadResult[]|null
     *
     * @throws RuntimeException on request failure
     */
    public function search(?string $firstname = null, ?string $lastname = null, ?int $professionId = null, ?array $disciplineIds = null): ?array
    {
        $params = [
            'searchField' => trim((($lastname ?? '') . ' ' . ($firstname ?? ''))),
        ];

        if ($professionId !== null) {
            $professions = $this->getProfessions();
            if (!isset($professions[$professionId])) {
                throw new \InvalidArgumentException("Invalid profession ID: {$professionId}");
            }
            $params['professionIds'] = $professionId;
        }

        if ($disciplineIds !== null && count($disciplineIds) > 0) {
            $disciplines = $this->getDisciplines();
            foreach ($disciplineIds as $disciplineId) {
                if (!isset($disciplines[$disciplineId])) {
                    throw new \InvalidArgumentException("Invalid discipline ID: {$disciplineId}");
                }
            }
            $params['disciplineIds'] = $disciplineIds;
        }

        $response = $this->get(self::BASE_URL . self::SEARCH_PATH, $params);

        $json = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to decode JSON response: " . json_last_error_msg());
        }

        $results = [];

        foreach ($json['items'] ?? [] as $item) {
            $professionDescription = $item['profession']['description'] ?? [];
            $profession = new EcadProfession(
                $item['profession']['id'] ?? '',
                $item['profession']['categoryCd'] ?? '',
                $item['profession']['code'] ?? '',
                [
                    'textFr' => $professionDescription['textFr'] ?? '',
                    'textNl' => $professionDescription['textNl'] ?? '',
                ]
            );

            $disciplines = [];
            foreach ($item['disciplines'] ?? [] as $discipline) {
                $disciplineDescription = $discipline['description'] ?? [];
                $disciplines[] = new EcadDiscipline(
                    $discipline['id'] ?? '',
                    $discipline['typeC'] ?? '',
                    $discipline['professionCategoryCd'] ?? '',
                    $discipline['businessTypeCode'] ?? '',
                    [
                        'textFr' => $disciplineDescription['textFr'] ?? '',
                        'textNl' => $disciplineDescription['textNl'] ?? '',
                    ]
                );
            }

            $results[] = new EcadResult(
                $item['lastName'] ?? '',
                $item['firstName'] ?? '',
                $profession,
                $disciplines,
                isset($item['visa']['dateFrom']) ? new \DateTime($item['visa']['dateFrom']) : new \DateTime(),
                $item['visa']['dispensation'] ?? false,
                $item['practiceAddress'] ?? []
            );
        }

        return $results;
    }

    /**
     * Execute a GET request and return the response body.
     *
     * @param  string $url
     * @param  array<string, string> $params
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function get(string $url, array $params = []): string
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; RizivClient/1.0)',
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("cURL request failed: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("Unexpected HTTP status code: {$httpCode}");
        }

        return (string) $response;
    }
}
