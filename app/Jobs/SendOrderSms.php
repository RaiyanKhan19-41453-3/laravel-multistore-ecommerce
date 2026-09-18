<?php

namespace App\Jobs;

use App\Services\SmsGateways\SmsGatewayFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendOrderSms implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $phone,
        public string $message,
        public ?string $context = null,
    ) {}

    public function tries(): int
    {
        return 3;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $gateway = SmsGatewayFactory::getDefault();

        if (! $gateway) {
            return;
        }

        if (! $gateway->send($this->phone, $this->message)) {
            throw new RuntimeException("SMS gateway [{$gateway->getName()}] failed to send.");
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning('Order SMS failed after retries', [
            'phone_tail' => substr($this->phone, -4),
            'context' => $this->context,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
