<?php

declare(strict_types=1);

namespace UniRate\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use UniRate\Client;
use UniRate\Exception\ApiException;
use UniRate\Exception\AuthenticationException;
use UniRate\Exception\InvalidCurrencyException;
use UniRate\Exception\InvalidDateException;
use UniRate\Exception\RateLimitException;
use UniRate\Model\HistoricalLimits;
use UniRate\Model\VatCountry;
use UniRate\Model\VatRates;

final class ClientTest extends TestCase
{
    /** @var list<RequestInterface> */
    private array $history = [];

    /**
     * @param list<Response> $responses
     */
    private function makeClient(array $responses): Client
    {
        $this->history = [];
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient([
            'handler' => $stack,
            'http_errors' => false,
        ]);
        return new Client(
            apiKey: 'test-key',
            baseUrl: 'https://api.unirateapi.com',
            timeout: 5.0,
            httpClient: $guzzle,
        );
    }

    private function lastRequest(): RequestInterface
    {
        self::assertNotEmpty($this->history, 'No HTTP request was made.');
        return $this->history[count($this->history) - 1]['request'];
    }

    /**
     * @return array<string,string>
     */
    private function lastQuery(): array
    {
        parse_str($this->lastRequest()->getUri()->getQuery(), $q);
        /** @var array<string,string> $q */
        return $q;
    }

    public function testGetRateReturnsFloatAndSendsQueryParams(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"rate": "0.9321"}'),
        ]);

        $rate = $client->getRate(from: 'usd', to: 'eur');

        self::assertIsFloat($rate);
        self::assertEqualsWithDelta(0.9321, $rate, 0.0001);

        $req = $this->lastRequest();
        self::assertStringEndsWith('/api/rates', $req->getUri()->getPath());
        self::assertSame('application/json', $req->getHeaderLine('Accept'));
        self::assertStringContainsString('unirate-php/', $req->getHeaderLine('User-Agent'));

        $q = $this->lastQuery();
        self::assertSame('USD', $q['from']);
        self::assertSame('EUR', $q['to']);
        self::assertSame('test-key', $q['api_key']);
    }

    public function testGetRateWithoutToReturnsMap(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"rates": {"EUR": "0.9", "GBP": "0.8"}}'),
        ]);
        $rates = $client->getRate(from: 'USD');
        self::assertIsArray($rates);
        self::assertEqualsWithDelta(0.9, $rates['EUR'], 0.0001);
        self::assertEqualsWithDelta(0.8, $rates['GBP'], 0.0001);
    }

    public function testConvert(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"result": "93.21"}'),
        ]);
        $amount = $client->convert(to: 'EUR', amount: 100, from: 'USD');
        self::assertIsFloat($amount);
        self::assertEqualsWithDelta(93.21, $amount, 0.01);

        $q = $this->lastQuery();
        self::assertSame('100', $q['amount']);
        self::assertSame('USD', $q['from']);
        self::assertSame('EUR', $q['to']);
    }

    public function testGetSupportedCurrencies(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"currencies": ["USD", "EUR", "GBP", "BTC"]}'),
        ]);
        $currencies = $client->getSupportedCurrencies();
        self::assertSame(['USD', 'EUR', 'GBP', 'BTC'], $currencies);
    }

    public function testHistoricalRate(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"rate": "0.8412"}'),
        ]);
        $rate = $client->getHistoricalRate(date: '2024-01-01', from: 'USD', to: 'EUR');
        self::assertEqualsWithDelta(0.8412, $rate, 0.0001);

        $q = $this->lastQuery();
        self::assertSame('2024-01-01', $q['date']);
        self::assertSame('USD', $q['from']);
        self::assertSame('EUR', $q['to']);
    }

    public function testConvertHistorical(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"result": "84.12"}'),
        ]);
        $result = $client->convertHistorical(
            amount: 100.0,
            from: 'USD',
            to: 'EUR',
            date: '2024-01-01',
        );
        self::assertIsFloat($result);
        self::assertEqualsWithDelta(84.12, $result, 0.01);
    }

    public function testGetTimeSeries(): void
    {
        $body = '{"data": {"2024-01-01": {"EUR": 0.90}, "2024-01-02": {"EUR": 0.91}}}';
        $client = $this->makeClient([
            new Response(200, [], $body),
        ]);
        $series = $client->getTimeSeries(
            startDate: '2024-01-01',
            endDate: '2024-01-02',
            base: 'USD',
            currencies: ['eur'],
        );
        self::assertIsArray($series);
        self::assertEqualsWithDelta(0.90, $series['2024-01-01']['EUR'], 0.0001);
        self::assertEqualsWithDelta(0.91, $series['2024-01-02']['EUR'], 0.0001);

        $q = $this->lastQuery();
        self::assertSame('EUR', $q['currencies']);
        self::assertSame('2024-01-01', $q['start_date']);
        self::assertSame('2024-01-02', $q['end_date']);
    }

    public function testHistoricalLimits(): void
    {
        $body = '{"total_currencies": 2, "currencies": {"USD": {"earliest_date": "1999-01-01", "latest_date": "2026-04-20", "total_days": 9000}, "EUR": {"earliest_date": "1999-01-04", "latest_date": "2026-04-20", "total_days": 8000}}}';
        $client = $this->makeClient([
            new Response(200, [], $body),
        ]);
        $limits = $client->getHistoricalLimits();
        self::assertInstanceOf(HistoricalLimits::class, $limits);
        self::assertSame(2, $limits->totalCurrencies);
        self::assertSame('1999-01-01', $limits->currencies['USD']->earliestDate);
        self::assertSame(9000, $limits->currencies['USD']->totalDays);
    }

    public function testGetVatRatesForCountry(): void
    {
        $body = '{"country": "DE", "vat_data": {"country_code": "DE", "country_name": "Germany", "vat_rate": 19.0}}';
        $client = $this->makeClient([
            new Response(200, [], $body),
        ]);
        $resp = $client->getVatRates(country: 'de');
        self::assertInstanceOf(VatCountry::class, $resp);
        self::assertSame('DE', $resp->country);
        self::assertSame('DE', $resp->vatData->countryCode);
        self::assertSame('Germany', $resp->vatData->countryName);
        self::assertEqualsWithDelta(19.0, $resp->vatData->vatRate, 0.001);

        $q = $this->lastQuery();
        self::assertSame('DE', $q['country']);
    }

    public function testGetVatRatesAllCountries(): void
    {
        $body = '{"date": "2026-01-22", "total_countries": 2, "vat_rates": {"DE": {"country_code": "DE", "country_name": "Germany", "vat_rate": 19.0}, "FR": {"country_code": "FR", "country_name": "France", "vat_rate": 20.0}}}';
        $client = $this->makeClient([
            new Response(200, [], $body),
        ]);
        $resp = $client->getVatRates();
        self::assertInstanceOf(VatRates::class, $resp);
        self::assertSame(2, $resp->totalCountries);
        self::assertSame('2026-01-22', $resp->date);
        self::assertEqualsWithDelta(19.0, $resp->vatRates['DE']->vatRate, 0.001);
        self::assertSame('France', $resp->vatRates['FR']->countryName);
    }

    public function testHistoricalPaywallMapsTo403ApiException(): void
    {
        $client = $this->makeClient([
            new Response(403, [], '{"error": "Historical data access requires a Pro subscription"}'),
        ]);
        try {
            $client->getHistoricalRate(date: '2024-01-01', from: 'USD', to: 'EUR');
            self::fail('Expected ApiException for 403');
        } catch (ApiException $e) {
            self::assertSame(403, $e->getStatusCode());
            self::assertStringContainsString('Pro', $e->getMessage());
            self::assertNotSame('', $e->getBody());
        }
    }

    public function testAuthenticationErrorOn401(): void
    {
        $client = $this->makeClient([new Response(401, [], '')]);
        $this->expectException(AuthenticationException::class);
        $client->getRate(to: 'EUR');
    }

    public function testRateLimitErrorOn429(): void
    {
        $client = $this->makeClient([new Response(429, [], '')]);
        $this->expectException(RateLimitException::class);
        $client->getRate(to: 'EUR');
    }

    public function testInvalidCurrencyErrorOn404(): void
    {
        $client = $this->makeClient([new Response(404, [], '')]);
        $this->expectException(InvalidCurrencyException::class);
        $client->getRate(to: 'ZZZ');
    }

    public function testInvalidDateErrorOn400(): void
    {
        $client = $this->makeClient([new Response(400, [], 'bad date')]);
        $this->expectException(InvalidDateException::class);
        $client->getHistoricalRate(date: 'not-a-date', to: 'EUR');
    }

    public function testApiKeyAlwaysSent(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"currencies": []}'),
        ]);
        $client->getSupportedCurrencies();
        $q = $this->lastQuery();
        self::assertSame('test-key', $q['api_key']);
    }
}
