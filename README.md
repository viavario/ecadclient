# viavario/ecadclient

A lightweight PHP client for searching Belgian healthcare professionals via the public eCad API.

## Requirements

- PHP `^7.4 | ^8.0`
- Extensions: `ext-curl`, `ext-dom`
- `psr/simple-cache` (installed automatically via Composer)

## Installation

```bash
composer require viavario/ecadclient
```

## What this library does

### `EcadClient`

`EcadClient` is responsible for:

- retrieving professions (`getProfessions()`)
- retrieving disciplines (`getDisciplines()`)
- searching healthcare professionals by name and optional filters (`search()`)
- optional caching (PSR-16 `CacheInterface`)

It communicates with:

- `https://apps.health.belgium.be/ecad-public-search-rs/professionals`
- `https://apps.health.belgium.be/ecad-public-search-rs/ref-data/professions`
- `https://apps.health.belgium.be/ecad-public-search-rs/ref-data/disciplines`

### Data objects

The client maps API responses to:

- `EcadResult`
- `EcadProfession`
- `EcadDiscipline`

Each provides a `toArray()` method for easier export/serialization.

### Optional file-based cache

This package includes `viavario\ecadclient\Cache\FileCache`, a simple PSR-16 file cache implementation.

You can pass any PSR-16 cache to `EcadClient`, including this one.

## Usage

### Basic search

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use viavario\ecadclient\EcadClient;

$client = new EcadClient();

$results = $client->search('John', 'Doe');

if (empty($results)) {
    echo "No healthcare professionals found." . PHP_EOL;
    exit;
}

foreach ($results as $result) {
    echo $result->firstname . ' ' . $result->lastname . PHP_EOL;
    echo 'Profession: ' . ($result->profession->description['textFr'] ?? $result->profession->description['textNl'] ?? $result->profession->code) . PHP_EOL;
    echo 'Visa date: ' . $result->visa['dateFrom']->format('Y-m-d') . PHP_EOL;
    echo PHP_EOL;
}
```

### Search with profession and discipline filters

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use viavario\ecadclient\EcadClient;

$client = new EcadClient();

$professionId = 1;         // Example ID (must exist in getProfessions())
$disciplineIds = [10, 12]; // Example IDs (must exist in getDisciplines())

$results = $client->search('Jane', 'Smith', $professionId, $disciplineIds);
```

### Using the built-in file cache

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use viavario\ecadclient\Cache\FileCache;
use viavario\ecadclient\EcadClient;

$cache = new FileCache(__DIR__ . '/var/cache/ecad', 3600); // cache for 1 hour
$client = new EcadClient($cache, 86400); // metadata cache TTL: 24h

$professions = $client->getProfessions(); // cached
$disciplines = $client->getDisciplines(); // cached
```

## API

### `EcadClient`

#### `__construct(?Psr\SimpleCache\CacheInterface $cache = null, int $ttl = 86400)`

- `$cache`: optional PSR-16 cache implementation
- `$ttl`: cache TTL in seconds for professions/disciplines lists

#### `getProfessions(bool $refresh = false): array<int, EcadProfession>`

Fetches all professions. Uses cache unless `$refresh = true`.

#### `getDisciplines(bool $refresh = false): array<int, EcadDiscipline>`

Fetches all disciplines. Uses cache unless `$refresh = true`.

#### `search(?string $firstname = null, ?string $lastname = null, ?int $professionId = null, ?array $disciplineIds = null): ?array`

Searches professionals and returns an array of `EcadResult` objects (or `null`).

Throws:

- `\InvalidArgumentException` for invalid profession/discipline IDs
- `\RuntimeException` for HTTP/cURL/JSON errors

### `EcadResult` properties

- `$lastname` (`string`)
- `$firstname` (`string`)
- `$profession` (`EcadProfession`)
- `$disciplines` (`EcadDiscipline[]`)
- `$visa` (`array{dateFrom: \DateTime, dispensation: bool}`)
- `$practiceAddress` (`array<int, array>`)

### `EcadProfession` properties

- `$id` (`int|string`)
- `$categoryCd` (`string`)
- `$code` (`string`)
- `$description` (`array{textFr: string, textNl: string}`)

### `EcadDiscipline` properties

- `$id` (`int|string`)
- `$typeC` (`string`)
- `$professionCategoryCd` (`string`)
- `$businessTypeCode` (`string`)
- `$description` (`array{textFr: string, textNl: string}`)

## Development

Install dependencies:

```bash
composer install
```

Run tests:

```bash
./vendor/bin/phpunit
```

## License

MIT — see [LICENSE](LICENSE) for details.

## Disclaimer

This package depends on public eCad API behavior and response format. If upstream endpoints or payloads change, client updates may be required.
