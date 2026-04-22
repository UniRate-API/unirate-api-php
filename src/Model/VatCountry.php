<?php

declare(strict_types=1);

namespace UniRate\Model;

/**
 * Response shape for /api/vat/rates?country=<code>.
 */
final class VatCountry
{
    public function __construct(
        public readonly ?string $country,
        public readonly VatRateEntry $vatData,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $vatData = $data['vat_data'] ?? [];
        return new self(
            country: isset($data['country']) ? (string) $data['country'] : null,
            vatData: VatRateEntry::fromArray(is_array($vatData) ? $vatData : []),
        );
    }
}
