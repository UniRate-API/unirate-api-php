<?php

declare(strict_types=1);

namespace UniRate;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface as Psr18ClientInterface;
use Psr\Http\Message\ResponseInterface;
use UniRate\Exception\ApiException;
use UniRate\Exception\AuthenticationException;
use UniRate\Exception\InvalidCurrencyException;
use UniRate\Exception\InvalidDateException;
use UniRate\Exception\RateLimitException;
use UniRate\Exception\UniRateException;
use UniRate\Model\HistoricalLimits;
use UniRate\Model\VatCountry;
use UniRate\Model\VatRates;

/**
 * PHP client for the UniRate API.
 *
 * Get a free API key at https://unirateapi.com.
 *
 * Usage:
 * ```
 * $client = new \UniRate\Client('your-api-key');
 * $rate = $client->getRate(to: 'EUR');
 * ```
 */
final class Client
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://api.unirateapi.com';

    private readonly GuzzleClientInterface|Psr18ClientInterface $httpClient;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly float $timeout = 30.0,
        GuzzleClientInterface|Psr18ClientInterface|null $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new GuzzleClient([
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
            'http_errors' => false,
        ]);
    }

    // -----------------------------------------------------------------
    // Current rates & conversion
    // -----------------------------------------------------------------

    /**
     * Fetch the current exchange rate.
     *
     * If `$to` is null, returns the full `array<string,float>` of rates
     * for the base currency. Otherwise returns a single float.
     *
     * When `$format` is set to anything other than `"json"`, returns the raw
     * response body as a string.
     *
     * @return float|array<string,float>|string
     */
    public function getRate(
        string $from = 'USD',
        ?string $to = null,
        string $format = 'json',
        ?string $callback = null,
    ): float|array|string {
        $query = ['from' => strtoupper($from)];
        if ($to !== null) {
            $query['to'] = strtoupper($to);
        }
        $data = $this->get('/api/rates', $query, $format, $callback);
        if (is_string($data)) {
            return $data;
        }
        if ($to !== null) {
            return (float) ($data['rate'] ?? 0.0);
        }
        return $this->castStringFloatMap($data['rates'] ?? []);
    }

    /**
     * Convert an amount using the current exchange rate.
     *
     * @return float|array<string,float>|string
     */
    public function convert(
        string $to,
        float $amount = 1.0,
        string $from = 'USD',
        string $format = 'json',
        ?string $callback = null,
    ): float|array|string {
        $query = [
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'amount' => $this->formatAmount($amount),
        ];
        $data = $this->get('/api/convert', $query, $format, $callback);
        if (is_string($data)) {
            return $data;
        }
        if (isset($data['result'])) {
            return (float) $data['result'];
        }
        return $this->castStringFloatMap($data['results'] ?? []);
    }

    /**
     * List all supported currency codes.
     *
     * @return list<string>|string
     */
    public function getSupportedCurrencies(
        string $format = 'json',
        ?string $callback = null,
    ): array|string {
        $data = $this->get('/api/currencies', [], $format, $callback);
        if (is_string($data)) {
            return $data;
        }
        $currencies = $data['currencies'] ?? [];
        $out = [];
        foreach ($currencies as $code) {
            $out[] = (string) $code;
        }
        return $out;
    }

    // -----------------------------------------------------------------
    // Historical data (Pro-gated)
    // -----------------------------------------------------------------

    /**
     * Fetch a historical exchange rate for a specific date.
     *
     * Shape depends on `$amount` and whether `$to` is set — see spec.
     *
     * @return float|array<string,float>|string
     */
    public function getHistoricalRate(
        string $date,
        float $amount = 1.0,
        string $from = 'USD',
        ?string $to = null,
        string $format = 'json',
        ?string $callback = null,
    ): float|array|string {
        $query = [
            'date' => $date,
            'from' => strtoupper($from),
            'amount' => $this->formatAmount($amount),
        ];
        if ($to !== null) {
            $query['to'] = strtoupper($to);
        }
        $data = $this->get('/api/historical/rates', $query, $format, $callback);
        if (is_string($data)) {
            return $data;
        }
        if (isset($data['rate'])) {
            return (float) $data['rate'];
        }
        if (isset($data['result'])) {
            return (float) $data['result'];
        }
        if (isset($data['rates'])) {
            return $this->castStringFloatMap($data['rates']);
        }
        return $this->castStringFloatMap($data['results'] ?? []);
    }

    /**
     * Fetch all historical rates for a base on a given date.
     *
     * @return array<string,float>|string
     */
    public function getHistoricalRates(
        string $date,
        float $amount = 1.0,
        string $base = 'USD',
        string $format = 'json',
        ?string $callback = null,
    ): array|string {
        $result = $this->getHistoricalRate(
            date: $date,
            amount: $amount,
            from: $base,
            to: null,
            format: $format,
            callback: $callback,
        );
        if (is_string($result) || is_array($result)) {
            return $result;
        }
        // Shouldn't happen without `to`, but fall back safely.
        return [];
    }

    /**
     * Convert using a historical rate. Thin alias over getHistoricalRate().
     */
    public function convertHistorical(
        float $amount,
        string $from,
        string $to,
        string $date,
        string $format = 'json',
        ?string $callback = null,
    ): float|string {
        $result = $this->getHistoricalRate(
            date: $date,
            amount: $amount,
            from: $from,
            to: $to,
            format: $format,
            callback: $callback,
        );
        if (is_string($result)) {
            return $result;
        }
        if (is_float($result) || is_int($result)) {
            return (float) $result;
        }
        // Defensive: unexpected shape.
        throw new UniRateException('Unexpected historical conversion response shape.');
    }

    /**
     * Fetch a time series of exchange rates (up to 5 years).
     *
     * Returns the raw `data` map (`date => [code => rate]`) to match the
     * other language clients. Pass `$format !== "json"` to get the raw body.
     *
     * @param list<string>|null $currencies
     *
     * @return array<string,array<string,float>>|string
     */
    public function getTimeSeries(
        string $startDate,
        string $endDate,
        float $amount = 1.0,
        string $base = 'USD',
        ?array $currencies = null,
        string $format = 'json',
        ?string $callback = null,
    ): array|string {
        $query = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'base' => strtoupper($base),
            'amount' => $this->formatAmount($amount),
        ];
        if ($currencies !== null && $currencies !== []) {
            $query['currencies'] = implode(
                ',',
                array_map(static fn ($c) => strtoupper((string) $c), $currencies),
            );
        }
        $data = $this->get('/api/historical/timeseries', $query, $format, $callback);
        if (is_string($data)) {
            return $data;
        }
        $out = [];
        foreach (($data['data'] ?? []) as $date => $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[(string) $date] = $this->castStringFloatMap($row);
        }
        return $out;
    }

    /**
     * Fetch the available historical-data coverage per currency.
     */
    public function getHistoricalLimits(
        string $format = 'json',
        ?string $callback = null,
    ): HistoricalLimits|string {
        $data = $this->get('/api/historical/limits', [], $format, $callback);
        if (is_string($data)) {
            return $data;
        }
        return HistoricalLimits::fromArray($data);
    }

    // -----------------------------------------------------------------
    // VAT
    // -----------------------------------------------------------------

    /**
     * Fetch VAT rates.
     *
     * With no `$country`, returns a `VatRates` struct covering every country.
     * With `$country`, returns a `VatCountry` struct for that country.
     */
    public function getVatRates(
        ?string $country = null,
        string $format = 'json',
        ?string $callback = null,
    ): VatRates|VatCountry|string {
        $query = [];
        if ($country !== null) {
            $query['country'] = strtoupper($country);
        }
        $data = $this->get('/api/vat/rates', $query, $format, $callback);
        if (is_string($data)) {
            return $data;
        }
        if ($country !== null) {
            return VatCountry::fromArray($data);
        }
        return VatRates::fromArray($data);
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Perform an authenticated GET request and decode JSON (or return raw body
     * when `$format !== "json"`).
     *
     * @param array<string,string> $query
     *
     * @return array<string,mixed>|string
     */
    private function get(
        string $path,
        array $query,
        string $format,
        ?string $callback,
    ): array|string {
        $query['api_key'] = $this->apiKey;
        if ($format !== 'json') {
            $query['format'] = $format;
        }
        if ($callback !== null) {
            $query['callback'] = $callback;
        }
        $url = rtrim($this->baseUrl, '/') . $path . '?' . http_build_query($query);

        $request = new Request(
            'GET',
            $url,
            [
                'Accept' => 'application/json',
                'User-Agent' => 'unirate-php/' . self::VERSION,
            ],
        );

        try {
            if ($this->httpClient instanceof GuzzleClientInterface) {
                $response = $this->httpClient->send($request, [
                    'timeout' => $this->timeout,
                    'connect_timeout' => $this->timeout,
                    'http_errors' => false,
                ]);
            } else {
                // PSR-18 path
                $response = $this->httpClient->sendRequest($request);
            }
        } catch (GuzzleException | \Psr\Http\Client\ClientExceptionInterface $e) {
            throw new UniRateException(
                'Network error: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        return $this->handleResponse($response, $format);
    }

    /**
     * @return array<string,mixed>|string
     */
    private function handleResponse(ResponseInterface $response, string $format): array|string
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status >= 200 && $status < 300) {
            if ($format !== 'json') {
                return $body;
            }
            /** @var mixed $decoded */
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                throw new UniRateException(
                    'Failed to decode response: expected JSON object, got: ' . substr($body, 0, 200),
                );
            }
            /** @var array<string,mixed> $decoded */
            return $decoded;
        }

        match (true) {
            $status === 400 => throw new InvalidDateException(
                $body !== '' ? $body : 'Invalid request parameters',
                $status,
            ),
            $status === 401 => throw new AuthenticationException(
                'Missing or invalid API key',
                $status,
            ),
            $status === 403 => throw new ApiException(
                'Endpoint requires a Pro subscription',
                $status,
                $body,
            ),
            $status === 404 => throw new InvalidCurrencyException(
                'Currency not found or no data available',
                $status,
            ),
            $status === 429 => throw new RateLimitException(
                'Rate limit exceeded',
                $status,
            ),
            $status === 503 => throw new ApiException(
                'Service unavailable',
                $status,
                $body,
            ),
            default => throw new ApiException(
                'API error (status ' . $status . '): ' . $body,
                $status,
                $body,
            ),
        };
    }

    /**
     * @param array<mixed,mixed> $map
     *
     * @return array<string,float>
     */
    private function castStringFloatMap(array $map): array
    {
        $out = [];
        foreach ($map as $key => $value) {
            $out[(string) $key] = (float) $value;
        }
        return $out;
    }

    /**
     * Format a float for the `amount` query parameter without scientific
     * notation or trailing zeros past what's meaningful.
     */
    private function formatAmount(float $amount): string
    {
        if ((float) (int) $amount === $amount) {
            return (string) (int) $amount;
        }
        // Use a locale-independent formatter and strip trailing zeros.
        $s = rtrim(rtrim(sprintf('%.8F', $amount), '0'), '.');
        return $s === '' ? '0' : $s;
    }
}
