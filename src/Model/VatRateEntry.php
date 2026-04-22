<?php

declare(strict_types=1);

namespace UniRate\Model;

/**
 * A single country's VAT rate record.
 */
final class VatRateEntry
{
    public function __construct(
        public readonly ?string $countryCode,
        public readonly ?string $countryName,
        public readonly float $vatRate,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            countryCode: isset($data['country_code']) ? (string) $data['country_code'] : null,
            countryName: isset($data['country_name']) ? (string) $data['country_name'] : null,
            vatRate: (float) ($data['vat_rate'] ?? 0.0),
        );
    }
}
