<?php

declare(strict_types=1);

namespace viavario\ecadclient\tests;

use PHPUnit\Framework\TestCase;
use viavario\ecadclient\EcadClient;
use viavario\ecadclient\EcadDiscipline;
use viavario\ecadclient\EcadProfession;
use viavario\ecadclient\EcadResult;

/**
 * Concrete subclass that lets tests inject fixed HTTP responses
 * without making real network calls.
 */
class TestableEcadClient extends EcadClient
{
    /** @var array<string, string> URL substring => response body */
    private array $responses = [];

    /**
     * Queue a fake HTTP response body for a URL substring.
     *
     * @param string $urlSubstring Substring to match in requested URL.
     * @param string $body         Response body returned for matching URLs.
     */
    public function queueResponse(string $urlSubstring, string $body): void
    {
        $this->responses[$urlSubstring] = $body;
    }

    /**
     * Return a queued response that matches the requested URL.
     *
     * @param string              $url    Requested URL.
     * @param array<string,mixed> $params Query parameters.
     *
     * @return string
     */
    protected function get(string $url, array $params = []): string
    {
        foreach ($this->responses as $substring => $body) {
            if (strpos($url, $substring) !== false) {
                return $body;
            }
        }

        throw new \RuntimeException("No queued response for URL: {$url}");
    }
}

/**
 * Test suite for EcadClient.
 */
class EcadClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    /**
     * Build fixture JSON for professions endpoint.
     *
     * @return string
     */
    private function professionsJson(): string
    {
        return json_encode([
            [
                'id'          => 1,
                'categoryCd'  => 'CAT1',
                'code'        => 'P001',
                'description' => ['textFr' => 'Médecin', 'textNl' => 'Arts'],
            ],
            [
                'id'          => 2,
                'categoryCd'  => 'CAT2',
                'code'        => 'P002',
                'description' => ['textFr' => 'Infirmier', 'textNl' => 'Verpleegkundige'],
            ],
        ]);
    }

    /**
     * Build fixture JSON for disciplines endpoint.
     *
     * @return string
     */
    private function disciplinesJson(): string
    {
        return json_encode([
            [
                'id'                    => 10,
                'typeC'                 => 'TYPE_A',
                'professionCategoryCd'  => 'CAT1',
                'businessTypeCode'      => 'BTC1',
                'description'           => ['textFr' => 'Chirurgie', 'textNl' => 'Chirurgie'],
            ],
            [
                'id'                    => 20,
                'typeC'                 => 'TYPE_B',
                'professionCategoryCd'  => 'CAT2',
                'businessTypeCode'      => 'BTC2',
                'description'           => ['textFr' => 'Pédiatrie', 'textNl' => 'Pediatrie'],
            ],
        ]);
    }

    /**
     * Build fixture JSON for professionals search endpoint.
     *
     * @return string
     */
    private function searchResultJson(): string
    {
        return json_encode([
            'items' => [
                [
                    'lastName'  => 'Dupont',
                    'firstName' => 'Jean',
                    'profession' => [
                        'id'          => 1,
                        'categoryCd'  => 'CAT1',
                        'code'        => 'P001',
                        'description' => ['textFr' => 'Médecin', 'textNl' => 'Arts'],
                    ],
                    'disciplines' => [
                        [
                            'id'                    => 10,
                            'typeC'                 => 'TYPE_A',
                            'professionCategoryCd'  => 'CAT1',
                            'businessTypeCode'      => 'BTC1',
                            'description'           => ['textFr' => 'Chirurgie', 'textNl' => 'Chirurgie'],
                        ],
                    ],
                    'visa' => [
                        'dateFrom'     => '2020-01-01',
                        'dispensation' => false,
                    ],
                    'practiceAddress' => [
                        ['street' => 'Rue de la Loi', 'city' => 'Brussels'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a test client with all default fixtures queued.
     *
     * @return TestableEcadClient
     */
    private function makeClient(): TestableEcadClient
    {
        $client = new TestableEcadClient(null, 86400);
        $client->queueResponse('professions', $this->professionsJson());
        $client->queueResponse('disciplines', $this->disciplinesJson());
        $client->queueResponse('professionals', $this->searchResultJson());

        return $client;
    }

    // -------------------------------------------------------------------------
    // getProfessions
    // -------------------------------------------------------------------------

    /**
     * It returns professions indexed by their ID.
     */
    public function testGetProfessionsReturnsIndexedArray(): void
    {
        $client      = $this->makeClient();
        $professions = $client->getProfessions();

        $this->assertIsArray($professions);
        $this->assertCount(2, $professions);
        $this->assertArrayHasKey(1, $professions);
        $this->assertArrayHasKey(2, $professions);
    }

    /**
     * It hydrates profession entries as EcadProfession instances.
     */
    public function testGetProfessionsReturnsEcadProfessionInstances(): void
    {
        $professions = $this->makeClient()->getProfessions();

        foreach ($professions as $profession) {
            $this->assertInstanceOf(EcadProfession::class, $profession);
        }
    }

    /**
     * It maps profession payload fields correctly.
     */
    public function testGetProfessionsPopulatesFields(): void
    {
        $professions = $this->makeClient()->getProfessions();
        $first       = $professions[1];

        $this->assertSame('CAT1', $first->categoryCd);
        $this->assertSame('P001', $first->code);
        $this->assertSame('Médecin', $first->description['textFr']);
        $this->assertSame('Arts', $first->description['textNl']);
    }

    /**
     * It serves professions from cache on subsequent calls.
     */
    public function testGetProfessionsUsesCache(): void
    {
        $cache = new \viavario\ecadclient\Cache\FileCache(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ecadclient_test_' . uniqid()
        );

        $client = new TestableEcadClient($cache, 3600);
        $client->queueResponse('professions', $this->professionsJson());
        $client->queueResponse('disciplines', $this->disciplinesJson());
        $client->queueResponse('professionals', $this->searchResultJson());

        // First call — populates cache.
        $first = $client->getProfessions();

        // Second call — a new client with no queued responses but the same
        // cache should serve results from cache without hitting the network.
        $cachedClient = new TestableEcadClient($cache, 3600);
        $second = $cachedClient->getProfessions();

        $this->assertEquals($first, $second);

        // Clean up.
        $cache->clear();
    }

    /**
     * It bypasses cache when refresh is requested.
     */
    public function testGetProfessionsRefreshBypassesCache(): void
    {
        $cache = new \viavario\ecadclient\Cache\FileCache(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ecadclient_test_' . uniqid()
        );

        $client = new TestableEcadClient($cache, 3600);
        $client->queueResponse('professions', $this->professionsJson());
        $client->queueResponse('disciplines', $this->disciplinesJson());
        $client->queueResponse('professionals', $this->searchResultJson());

        $client->getProfessions(); // fill cache
        $refreshed = $client->getProfessions(true); // bypass cache

        $this->assertCount(2, $refreshed);

        $cache->clear();
    }

    /**
     * It throws when professions endpoint returns invalid JSON.
     */
    public function testGetProfessionsThrowsOnInvalidJson(): void
    {
        $client = new TestableEcadClient();
        $client->queueResponse('professions', 'not-json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to decode JSON/');

        $client->getProfessions();
    }

    // -------------------------------------------------------------------------
    // getDisciplines
    // -------------------------------------------------------------------------

    /**
     * It returns disciplines indexed by their ID.
     */
    public function testGetDisciplinesReturnsIndexedArray(): void
    {
        $disciplines = $this->makeClient()->getDisciplines();

        $this->assertIsArray($disciplines);
        $this->assertCount(2, $disciplines);
        $this->assertArrayHasKey(10, $disciplines);
        $this->assertArrayHasKey(20, $disciplines);
    }

    /**
     * It hydrates discipline entries as EcadDiscipline instances.
     */
    public function testGetDisciplinesReturnsEcadDisciplineInstances(): void
    {
        foreach ($this->makeClient()->getDisciplines() as $discipline) {
            $this->assertInstanceOf(EcadDiscipline::class, $discipline);
        }
    }

    /**
     * It maps discipline payload fields correctly.
     */
    public function testGetDisciplinesPopulatesFields(): void
    {
        $disciplines = $this->makeClient()->getDisciplines();
        $first       = $disciplines[10];

        $this->assertSame('TYPE_A', $first->typeC);
        $this->assertSame('CAT1', $first->professionCategoryCd);
        $this->assertSame('BTC1', $first->businessTypeCode);
        $this->assertSame('Chirurgie', $first->description['textFr']);
    }

    /**
     * It throws when disciplines endpoint returns invalid JSON.
     */
    public function testGetDisciplinesThrowsOnInvalidJson(): void
    {
        $client = new TestableEcadClient();
        $client->queueResponse('disciplines', 'not-json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to decode JSON/');

        $client->getDisciplines();
    }

    /**
     * It bypasses disciplines cache when refresh is requested.
     */
    public function testGetDisciplinesRefreshBypassesCache(): void
    {
        $cache = new \viavario\ecadclient\Cache\FileCache(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ecadclient_test_' . uniqid()
        );

        $client = new TestableEcadClient($cache, 3600);
        $client->queueResponse('professions', $this->professionsJson());
        $client->queueResponse('disciplines', $this->disciplinesJson());
        $client->queueResponse('professionals', $this->searchResultJson());

        $client->getDisciplines();
        $refreshed = $client->getDisciplines(true);

        $this->assertCount(2, $refreshed);

        $cache->clear();
    }

    // -------------------------------------------------------------------------
    // search
    // -------------------------------------------------------------------------

    /**
     * It returns a list of EcadResult instances.
     */
    public function testSearchReturnsArrayOfEcadResults(): void
    {
        $results = $this->makeClient()->search('Jean', 'Dupont');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        foreach ($results as $result) {
            $this->assertInstanceOf(EcadResult::class, $result);
        }
    }

    /**
     * It maps first and last names correctly.
     */
    public function testSearchMapsNameCorrectly(): void
    {
        $results = $this->makeClient()->search('Jean', 'Dupont');
        $first   = $results[0];

        $this->assertSame('Dupont', $first->lastname);
        $this->assertSame('Jean', $first->firstname);
    }

    /**
     * It maps nested profession data correctly.
     */
    public function testSearchMapsProfessionCorrectly(): void
    {
        $results    = $this->makeClient()->search();
        $profession = $results[0]->profession;

        $this->assertInstanceOf(EcadProfession::class, $profession);
        $this->assertSame('P001', $profession->code);
    }

    /**
     * It maps nested disciplines data correctly.
     */
    public function testSearchMapsDisciplinesCorrectly(): void
    {
        $results     = $this->makeClient()->search();
        $disciplines = $results[0]->disciplines;

        $this->assertCount(1, $disciplines);
        $this->assertInstanceOf(EcadDiscipline::class, $disciplines[0]);
        $this->assertSame('BTC1', $disciplines[0]->businessTypeCode);
    }

    /**
     * It maps visa data including date conversion.
     */
    public function testSearchMapsVisaCorrectly(): void
    {
        $results = $this->makeClient()->search();
        $visa    = $results[0]->visa;

        $this->assertInstanceOf(\DateTime::class, $visa['dateFrom']);
        $this->assertSame('2020-01-01', $visa['dateFrom']->format('Y-m-d'));
        $this->assertFalse($visa['dispensation']);
    }

    /**
     * It maps practice address information correctly.
     */
    public function testSearchMapsPracticeAddressCorrectly(): void
    {
        $results = $this->makeClient()->search();
        $address = $results[0]->practiceAddress;

        $this->assertCount(1, $address);
        $this->assertSame('Brussels', $address[0]['city']);
    }

    /**
     * It returns an empty array when no search items are returned.
     */
    public function testSearchReturnsEmptyArrayWhenNoItems(): void
    {
        $client = new TestableEcadClient();
        $client->queueResponse('professions', $this->professionsJson());
        $client->queueResponse('disciplines', $this->disciplinesJson());
        $client->queueResponse('professionals', json_encode(['items' => []]));

        $results = $client->search('Nobody');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * It accepts a valid profession ID filter.
     */
    public function testSearchWithValidProfessionIdFilters(): void
    {
        $results = $this->makeClient()->search(null, null, 1);

        $this->assertIsArray($results);
    }

    /**
     * It throws for an unknown profession ID filter.
     */
    public function testSearchThrowsOnInvalidProfessionId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid profession ID/');

        $this->makeClient()->search(null, null, 999);
    }

    /**
     * It accepts valid discipline ID filters.
     */
    public function testSearchWithValidDisciplineIdsFilters(): void
    {
        $results = $this->makeClient()->search(null, null, null, [10]);

        $this->assertIsArray($results);
    }

    /**
     * It throws for unknown discipline ID filters.
     */
    public function testSearchThrowsOnInvalidDisciplineId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid discipline ID/');

        $this->makeClient()->search(null, null, null, [999]);
    }

    /**
     * It throws when the search endpoint returns invalid JSON.
     */
    public function testSearchThrowsOnInvalidJson(): void
    {
        $client = new TestableEcadClient();
        $client->queueResponse('professions', $this->professionsJson());
        $client->queueResponse('disciplines', $this->disciplinesJson());
        $client->queueResponse('professionals', 'not-json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to decode JSON/');

        $client->search('Jean');
    }

    /**
     * It allows null names and builds an empty search field.
     */
    public function testSearchWithNullNamesBuildsEmptySearchField(): void
    {
        // Should not throw; searchField will be an empty string after trim.
        $results = $this->makeClient()->search(null, null);

        $this->assertIsArray($results);
    }
}

