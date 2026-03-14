<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Throwable;
use viavario\ecadclient\Cache\FileCache;
use viavario\ecadclient\EcadClient;

/**
 * Minimal runnable example for the ECAD client.
 *
 * Run with:
 * php example.php
 */

$professionId = 50013000001;
$disciplineId = 50000000001;

$cache = new FileCache(__DIR__ . '/cache');
$client = new EcadClient($cache);

try {
    echo "=== Loading reference data ===\n";

    $professions = $client->getProfessions();
    $disciplines = $client->getDisciplines();

    echo 'Profession [' . $professionId . ']: ';
    var_dump($professions[$professionId] ?? 'Not found');

    echo 'Discipline [' . $disciplineId . ']: ';
    var_dump($disciplines[$disciplineId] ?? 'Not found');

    echo "\n=== Search example ===\n";
    $results = $client->search('Tim', 'Vos', $professionId, [$disciplineId]);

    echo 'Found records: ' . count($results) . "\n";
    print_r(array_slice($results, 0, 3)); // show first 3 results only
} catch (Throwable $e) {
    fwrite(STDERR, 'ECAD request failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}