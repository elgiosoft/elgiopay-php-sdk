<?php

namespace ElgioPay\SDK;

readonly class PaymentResponse
{
    public function __construct(
        public string $transaction_id,
        public string $status,
        public float $amount,
        public string $currency,
        public string $payment_method,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $payment_url = null,
        public ?string $qr_code = null,
        public ?array $metadata = null,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            transaction_id: $data['transaction_id'] ?? throw new \InvalidArgumentException('transaction_id is required'),
            status: $data['status'] ?? throw new \InvalidArgumentException('status is required'),
            amount: (float) ($data['amount'] ?? throw new \InvalidArgumentException('amount is required')),
            currency: $data['currency'] ?? throw new \InvalidArgumentException('currency is required'),
            payment_method: $data['payment_method'] ?? throw new \InvalidArgumentException('payment_method is required'),
            reference: $data['reference'] ?? null,
            description: $data['description'] ?? null,
            payment_url: $data['payment_url'] ?? null,
            qr_code: $data['qr_code'] ?? null,
            metadata: $data['metadata'] ?? null,
            created_at: $data['created_at'] ?? null,
            updated_at: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'description' => $this->description,
            'payment_url' => $this->payment_url,
            'qr_code' => $this->qr_code,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}