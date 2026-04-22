<?php

declare(strict_types=1);

namespace UniRate\Model;

/**
 * Historical-data coverage for a single currency.
 */
final class HistoricalLimit
{
    public function __construct(
        public readonly string $earliestDate,
        public readonly string $latestDate,
        public readonly ?int $totalDays = null,
        public readonly ?string $description = null,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            earliestDate: (string) ($data['earliest_date'] ?? ''),
            latestDate: (string) ($data['latest_date'] ?? ''),
            totalDays: isset($data['total_days']) ? (int) $data['total_days'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
        );
    }
}
