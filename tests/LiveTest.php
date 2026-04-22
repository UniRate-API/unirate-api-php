<?php

declare(strict_types=1);

namespace UniRate\Tests;

use PHPUnit\Framework\TestCase;
use UniRate\Client;
use UniRate\Model\VatCountry;
use UniRate\Model\VatRates;

/**
 * Live tests — exercise only free-tier endpoints:
 *   /api/rates, /api/convert, /api/currencies, /api/vat/rates
 *
 * Historical + time-series + limits are Pro-gated and return 403 on free
 * keys; we deliberately skip them here to keep CI green.
 *
 * Run with:
 *   UNIRATE_API_KEY=... vendor/bin/phpunit --testsuite live
 */
final class LiveTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $apiKey = getenv('UNIRATE_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            $this->markTestSkipped('UNIRATE_API_KEY not set; skipping live tests.');
        }
        $this->client = new Client(apiKey: $apiKey);
    }

    public function testLiveGetRate(): void
    {
        $rate = $this->client->getRate(from: 'USD', to: 'EUR');
        self::assertIsFloat($rate);
        self::assertGreaterThan(0.0, $rate);
    }

    public function testLiveGetAllRatesForBase(): void
    {
        $rates = $this->client->getRate(from: 'USD');
        self::assertIsArray($rates);
        self::assertNotEmpty($rates);
        self::assertArrayHasKey('EUR', $rates);
    }

    public function testLiveConvert(): void
    {
        $result = $this->client->convert(to: 'EUR', amount: 100.0, from: 'USD');
        self::assertIsFloat($result);
        self::assertGreaterThan(0.0, $result);
    }

    public function testLiveGetSupportedCurrencies(): void
    {
        $currencies = $this->client->getSupportedCurrencies();
        self::assertIsArray($currencies);
        self::assertContains('USD', $currencies);
        self::assertContains('EUR', $currencies);
        self::assertGreaterThan(50, count($currencies));
    }

    public function testLiveGetVatRatesAllCountries(): void
    {
        $resp = $this->client->getVatRates();
        self::assertInstanceOf(VatRates::class, $resp);
        self::assertGreaterThan(0, $resp->totalCountries);
    }

    public function testLiveGetVatRateForCountry(): void
    {
        $resp = $this->client->getVatRates(country: 'DE');
        self::assertInstanceOf(VatCountry::class, $resp);
        self::assertSame('DE', $resp->vatData->countryCode);
        self::assertGreaterThan(0.0, $resp->vatData->vatRate);
    }
}
