<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use UniRate\Client;
use UniRate\Exception\UniRateException;

$apiKey = getenv('UNIRATE_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Set UNIRATE_API_KEY in your environment.\n");
    exit(1);
}

$client = new Client(apiKey: $apiKey);

try {
    // Current rate: USD -> EUR
    $rate = $client->getRate(from: 'USD', to: 'EUR');
    printf("1 USD = %.4f EUR\n", (float) $rate);

    // Convert 100 USD to GBP
    $converted = $client->convert(to: 'GBP', amount: 100, from: 'USD');
    printf("100 USD = %.2f GBP\n", (float) $converted);

    // List supported currencies
    $currencies = $client->getSupportedCurrencies();
    if (is_array($currencies)) {
        printf("Supported currencies: %d\n", count($currencies));
        printf("First 10: %s\n", implode(', ', array_slice($currencies, 0, 10)));
    }
} catch (UniRateException $e) {
    fwrite(STDERR, "UniRate error: " . $e->getMessage() . "\n");
    exit(2);
}
