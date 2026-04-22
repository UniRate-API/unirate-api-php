<?php

declare(strict_types=1);

namespace UniRate\Model;

/**
 * Response shape for /api/historical/limits.
 */
final class HistoricalLimits
{
    /**
     * @param array<string,HistoricalLimit> $currencies
     */
    public function __construct(
        public readonly int $totalCurrencies,
        public readonly array $currencies,
        public readonly ?string $dataSource = null,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $currencies = [];
        foreach (($data['currencies'] ?? []) as $code => $entry) {
            if (is_array($entry)) {
                $currencies[(string) $code] = HistoricalLimit::fromArray($entry);
            }
        }
        return new self(
            totalCurrencies: (int) ($data['total_currencies'] ?? count($currencies)),
            currencies: $currencies,
            dataSource: isset($data['data_source']) ? (string) $data['data_source'] : null,
        );
    }
}
