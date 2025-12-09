<?php

namespace ElgioPay\SDK;

readonly class PaymentRequest
{
    public function __construct(
        public float $amount,
        public string $payment_method,
        public string $customer_phone,
        public string $currency = 'XAF',
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $callback_url = null,
        public ?string $return_url = null,
        public ?array $metadata = null
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'customer_phone' => $this->customer_phone,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'description' => $this->description,
            'callback_url' => $this->callback_url,
            'return_url' => $this->return_url,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }
}