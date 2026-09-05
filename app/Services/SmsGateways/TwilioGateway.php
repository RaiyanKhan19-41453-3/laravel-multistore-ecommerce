<?php

namespace App\Services\SmsGateways;

use Illuminate\Support\Facades\Http;

class TwilioGateway implements SmsGateway
{
    private string $accountSid;

    private string $authToken;

    private string $fromNumber;

    private string $baseUrl = 'https://api.twilio.com/2010-04-01';

    public function __construct()
    {
        $this->accountSid = config('sms.gateways.twilio.account_sid', '');
        $this->authToken = config('sms.gateways.twilio.auth_token', '');
        $this->fromNumber = config('sms.gateways.twilio.from_number', '');
    }

    public function send(string $phone, string $message): bool
    {
        $url = "{$this->baseUrl}/Accounts/{$this->accountSid}/Messages.json";

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->post($url, [
                'To' => $phone,
                'From' => $this->fromNumber,
                'Body' => $message,
            ]);

        return $response->successful();
    }

    public function getName(): string
    {
        return 'twilio';
    }

    public function isConfigured(): bool
    {
        return filled($this->accountSid)
            && filled($this->authToken)
            && filled($this->fromNumber);
    }
}
