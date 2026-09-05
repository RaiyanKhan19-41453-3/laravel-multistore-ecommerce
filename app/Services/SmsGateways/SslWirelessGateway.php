<?php

namespace App\Services\SmsGateways;

use Illuminate\Support\Facades\Http;

class SslWirelessGateway implements SmsGateway
{
    private string $apiToken;

    private string $sid;

    private string $baseUrl = 'https://smsplus.sslwireless.com';

    public function __construct()
    {
        $this->apiToken = config('sms.gateways.ssl_wireless.api_token', '');
        $this->sid = config('sms.gateways.ssl_wireless.sid', '');
    }

    public function send(string $phone, string $message): bool
    {
        $url = "{$this->baseUrl}/api/v3/send-sms";

        $response = Http::post($url, [
            'sid' => $this->sid,
            'token' => $this->apiToken,
            'msisdn' => $phone,
            'sms' => $message,
            'csms_id' => uniqid(),
        ]);

        $data = $response->json();

        return $response->successful() && ($data['status'] ?? '') === 'success';
    }

    public function getName(): string
    {
        return 'ssl_wireless';
    }

    public function isConfigured(): bool
    {
        return filled($this->apiToken) && filled($this->sid);
    }
}
