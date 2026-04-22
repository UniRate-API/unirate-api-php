<?php

declare(strict_types=1);

namespace UniRate\Model;

/**
 * Convenience wrapper for time-series responses.
 *
 * The client returns `data` directly (`array<string, array<string, float>>`)
 * to match Python/Node/Swift, but this type is available for callers who
 * prefer a structured object.
 */
final class TimeSeriesData
{
    /**
     * @param list<string> $currencies
     * @param array<string,array<string,float>> $data
     */
    public function __construct(
        public readonly string $base,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $totalDays,
        public readonly array $currencies,
        public readonly array $data,
        public readonly float $amount = 1.0,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = [];
        foreach (($payload['data'] ?? []) as $date => $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = [];
            foreach ($row as $code => $value) {
                $normalized[(string) $code] = (float) $value;
            }
            $data[(string) $date] = $normalized;
        }
        $currencies = [];
        foreach (($payload['currencies'] ?? []) as $c) {
            $currencies[] = (string) $c;
        }
        return new self(
            base: (string) ($payload['base'] ?? 'USD'),
            startDate: (string) ($payload['start_date'] ?? ''),
            endDate: (string) ($payload['end_date'] ?? ''),
            totalDays: (int) ($payload['total_days'] ?? count($data)),
            currencies: $currencies,
            data: $data,
            amount: (float) ($payload['amount'] ?? 1.0),
        );
    }
}
