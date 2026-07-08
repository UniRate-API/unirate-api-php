# UniRate PHP Client

Official PHP client for the [UniRate API](https://unirateapi.com) — free, real-time and historical currency exchange rates plus VAT rates.

- Real-time exchange rates between 170+ currencies (fiat + crypto)
- Historical rates back to 1999
- Time-series ranges up to 5 years
- Currency conversion (current and historical)
- VAT rates for countries worldwide
- Free tier, no credit card required
- PHP 8.1+ with strict types and readonly value objects
- Guzzle 7 under the hood; swappable PSR-18 HTTP client for tests

## Requirements

- PHP 8.1+
- ext-json
- [Composer](https://getcomposer.org/)

## Installation

```bash
composer require unirate-api/unirate-api
```

## Quick start

```php
<?php
require 'vendor/autoload.php';

use UniRate\Client;

$client = new Client(apiKey: 'your-api-key');

// Current rate
$rate = $client->getRate(from: 'USD', to: 'EUR');
echo "USD -> EUR: $rate\n";

// Convert an amount
$euros = $client->convert(to: 'EUR', amount: 100, from: 'USD');
echo "100 USD = $euros EUR\n";

// All supported currencies
$currencies = $client->getSupportedCurrencies();
echo count($currencies) . " currencies supported\n";
```

Get a free API key at [https://unirateapi.com](https://unirateapi.com).

## API

### Current rates

```php
// Single pair -> float
$rate = $client->getRate(from: 'USD', to: 'EUR');

// All rates for a base -> array<string, float>
$rates = $client->getRate(from: 'USD');

// Convert an amount -> float
$result = $client->convert(to: 'EUR', amount: 100, from: 'USD');

// Supported currencies -> list<string>
$codes = $client->getSupportedCurrencies();
```

### Historical data (Pro subscription required)

```php
// Rate on a specific date
$rate = $client->getHistoricalRate(
    date: '2024-01-01',
    from: 'USD',
    to: 'EUR',
);

// All rates on a date for a base
$rates = $client->getHistoricalRates(date: '2024-01-01', base: 'USD');

// Convert using a historical rate
$amount = $client->convertHistorical(
    amount: 100,
    from: 'USD',
    to: 'EUR',
    date: '2024-01-01',
);

// Time series
$series = $client->getTimeSeries(
    startDate: '2024-01-01',
    endDate: '2024-01-07',
    base: 'USD',
    currencies: ['EUR', 'GBP'],
);

// Available historical coverage per currency
$limits = $client->getHistoricalLimits();
echo $limits->totalCurrencies;
```

### VAT rates

```php
// All countries -> VatRates
$vat = $client->getVatRates();
foreach ($vat->vatRates as $code => $entry) {
    echo "$code: {$entry->vatRate}%\n";
}

// Single country (ISO-3166 alpha-2) -> VatCountry
$de = $client->getVatRates(country: 'DE');
echo "Germany VAT: {$de->vatData->vatRate}%\n";
```

## Error handling

All methods throw exceptions inheriting from `UniRate\Exception\UniRateException`:

| HTTP status | Exception class |
|---|---|
| 400 | `InvalidDateException` |
| 401 | `AuthenticationException` |
| 403 | `ApiException` (Pro-gated endpoint on free tier) |
| 404 | `InvalidCurrencyException` |
| 429 | `RateLimitException` |
| other 4xx/5xx | `ApiException` |
| network / transport | `UniRateException` (base) |

```php
use UniRate\Exception\AuthenticationException;
use UniRate\Exception\ApiException;
use UniRate\Exception\InvalidCurrencyException;
use UniRate\Exception\RateLimitException;

try {
    $rate = $client->getRate(from: 'USD', to: 'ZZZ');
} catch (AuthenticationException $e) {
    // invalid API key
} catch (InvalidCurrencyException $e) {
    // unknown currency code
} catch (RateLimitException $e) {
    // back off and retry
} catch (ApiException $e) {
    printf("HTTP %d: %s\n", $e->getStatusCode(), $e->getBody());
}
```

## Advanced — dependency injection for tests

The constructor accepts any Guzzle `ClientInterface` or PSR-18 `ClientInterface` in the optional `httpClient` argument. The test suite uses Guzzle's `MockHandler` to exercise every method without touching the network:

```php
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use UniRate\Client;

$mock = new MockHandler([new Response(200, [], '{"rate": "0.9"}')]);
$guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

$client = new Client(apiKey: 'test', httpClient: $guzzle);
```

## Rate limits

- **Currency endpoints:** standard rate limits apply
- **Historical endpoints:** 50 requests/hour on the free tier
- **VAT endpoints:** 1800 requests/hour on the free tier

## Running the tests

```bash
composer install
vendor/bin/phpunit --testsuite mock                        # mock suite (no network)
UNIRATE_API_KEY=... vendor/bin/phpunit --testsuite live    # free-tier live suite
```

## Related clients

- [unirate-api-python](https://github.com/UniRate-API/unirate-api-python) (PyPI: `unirate-api`)
- [unirate-api-nodejs](https://github.com/UniRate-API/unirate-api-nodejs) (npm: `unirate-api`)
- [unirate-api-swift](https://github.com/UniRate-API/unirate-api-swift)

<!-- unirate-ecosystem-footer:start -->
## UniRate ecosystem

UniRate ships official integrations for 40+ ecosystems, all maintained under the
[UniRate-API](https://github.com/UniRate-API) org.

**Core clients (9 languages)**
[Python](https://github.com/UniRate-API/unirate-api-python) ·
[Node.js / TypeScript](https://github.com/UniRate-API/unirate-api-nodejs) ·
[Go](https://github.com/UniRate-API/unirate-api-go) ·
[Rust](https://github.com/UniRate-API/unirate-api-rust) ·
[Java](https://github.com/UniRate-API/unirate-api-java) ·
[Ruby](https://github.com/UniRate-API/unirate-api-ruby) ·
[PHP](https://github.com/UniRate-API/unirate-api-php) ·
[.NET](https://github.com/UniRate-API/unirate-api-dotnet) ·
[Swift](https://github.com/UniRate-API/unirate-api-swift)

**JavaScript / TypeScript**
[React](https://github.com/UniRate-API/react-unirate) ·
[Next.js](https://github.com/UniRate-API/next-unirate) ·
[Remix](https://github.com/UniRate-API/remix-unirate) ·
[SvelteKit](https://github.com/UniRate-API/sveltekit-unirate) ·
[Vue](https://github.com/UniRate-API/vue-unirate) ·
[Angular](https://github.com/UniRate-API/angular-unirate) ·
[Nuxt](https://github.com/UniRate-API/nuxt-unirate) ·
[NestJS](https://github.com/UniRate-API/nestjs-unirate) ·
[tRPC](https://github.com/UniRate-API/trpc-unirate)

**Static-site generators**
[Astro](https://github.com/UniRate-API/astro-unirate) ·
[Eleventy](https://github.com/UniRate-API/eleventy-unirate) ·
[Hugo](https://github.com/UniRate-API/hugo-unirate) ·
[Jekyll](https://github.com/UniRate-API/jekyll-unirate)

**CMS & e-commerce**
[Wagtail](https://github.com/UniRate-API/wagtail-unirate) ·
[WordPress](https://github.com/UniRate-API/unirate-currency-converter) ·
[WooCommerce](https://github.com/UniRate-API/unirate-woocs) ·
[Drupal](https://github.com/UniRate-API/drupal-unirate) ·
[Strapi](https://github.com/UniRate-API/strapi-plugin-unirate) ·
[Medusa](https://github.com/UniRate-API/medusa-plugin-unirate) ·
[Symfony](https://github.com/UniRate-API/unirate-bundle) ·
[Laravel](https://github.com/UniRate-API/laravel-money-unirate) ·
[Directus](https://github.com/UniRate-API/directus-extension-unirate)

**Data, AI & backend**
[LangChain (Python)](https://github.com/UniRate-API/langchain-unirate) ·
[LangChain.js](https://github.com/UniRate-API/langchain-js-unirate) ·
[FastAPI](https://github.com/UniRate-API/fastapi-unirate) ·
[Flask](https://github.com/UniRate-API/flask-unirate) ·
[Django REST Framework](https://github.com/UniRate-API/djangorestframework-unirate) ·
[Apache Airflow](https://github.com/UniRate-API/airflow-provider-unirate) ·
[dbt](https://github.com/UniRate-API/dbt-unirate)

**Platform & tools**
[MCP server](https://github.com/UniRate-API/unirate-mcp) ·
[CLI](https://github.com/UniRate-API/unirate-cli) ·
[Cloudflare Workers](https://github.com/UniRate-API/cloudflare-workers-unirate) ·
[Home Assistant](https://github.com/UniRate-API/unirate-home-assistant) ·
[n8n](https://github.com/UniRate-API/n8n-nodes-unirate) ·
[Google Sheets](https://github.com/UniRate-API/unirate-sheets) ·
[VS Code](https://github.com/UniRate-API/vscode-unirate) ·
[Obsidian](https://github.com/UniRate-API/obsidian-currency)

**Money library bridges**
[money gem (Ruby)](https://github.com/UniRate-API/money-unirate-api) ·
[NodaMoney (.NET)](https://github.com/UniRate-API/UniRateApi.NodaMoney)

Get a free API key at [unirateapi.com](https://unirateapi.com).
<!-- unirate-ecosystem-footer:end -->

## License

MIT — see [LICENSE](LICENSE).