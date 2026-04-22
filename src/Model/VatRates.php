<?php

declare(strict_types=1);

namespace UniRate\Model;

/**
 * Response shape for /api/vat/rates (no country filter).
 */
final class VatRates
{
    /**
     * @param array<string,VatRateEntry> $vatRates
     */
    public function __construct(
        public readonly int $totalCountries,
        public readonly array $vatRates,
        public readonly ?string $date = null,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rates = [];
        foreach (($data['vat_rates'] ?? []) as $code => $entry) {
            if (is_array($entry)) {
                $rates[(string) $code] = VatRateEntry::fromArray($entry);
            }
        }
        return new self(
            totalCountries: (int) ($data['total_countries'] ?? count($rates)),
            vatRates: $rates,
            date: isset($data['date']) ? (string) $data['date'] : null,
        );
    }
}
