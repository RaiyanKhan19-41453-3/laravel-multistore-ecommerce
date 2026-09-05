<?php

use App\Services\SmsGateways\SmsGatewayFactory;
use App\Services\SmsGateways\SslWirelessGateway;
use App\Services\SmsGateways\TwilioGateway;

it('factory throws for unsupported gateway', function () {
    SmsGatewayFactory::make('nonexistent');
})->throws(InvalidArgumentException::class, 'SMS gateway [nonexistent] is not supported.');

it('factory throws for unconfigured gateway', function () {
    config(['sms.enabled.twilio' => true]);
    config(['sms.gateways.twilio' => ['account_sid' => '', 'auth_token' => '', 'from_number' => '']]);

    SmsGatewayFactory::make('twilio');
})->throws(InvalidArgumentException::class, 'SMS gateway [twilio] is not configured.');

it('factory creates twilio gateway when configured', function () {
    config(['sms.enabled.twilio' => true]);
    config(['sms.gateways.twilio' => [
        'account_sid' => 'AC123',
        'auth_token' => 'auth_token_123',
        'from_number' => '+1234567890',
    ]]);

    $gateway = SmsGatewayFactory::make('twilio');

    expect($gateway)->toBeInstanceOf(TwilioGateway::class);
    expect($gateway->getName())->toBe('twilio');
    expect($gateway->isConfigured())->toBeTrue();
});

it('factory creates ssl_wireless gateway when configured', function () {
    config(['sms.enabled.ssl_wireless' => true]);
    config(['sms.gateways.ssl_wireless' => [
        'api_token' => 'api_token_123',
        'sid' => 'MyApp',
    ]]);

    $gateway = SmsGatewayFactory::make('ssl_wireless');

    expect($gateway)->toBeInstanceOf(SslWirelessGateway::class);
    expect($gateway->getName())->toBe('ssl_wireless');
    expect($gateway->isConfigured())->toBeTrue();
});

it('getEnabled returns only configured and enabled gateways', function () {
    config(['sms.enabled' => ['twilio' => true, 'ssl_wireless' => true]]);
    config(['sms.gateways.twilio' => [
        'account_sid' => 'AC123',
        'auth_token' => 'auth_token_123',
        'from_number' => '+1234567890',
    ]]);
    config(['sms.gateways.ssl_wireless' => ['api_token' => '', 'sid' => '']]);

    $enabled = SmsGatewayFactory::getEnabled();

    expect($enabled)->toContain('twilio');
    expect($enabled)->not->toContain('ssl_wireless');
});

it('getEnabled returns empty when none enabled', function () {
    config(['sms.enabled' => ['twilio' => false, 'ssl_wireless' => false]]);

    expect(SmsGatewayFactory::getEnabled())->toBeEmpty();
});

it('isEnabled returns true for enabled gateway', function () {
    config(['sms.enabled' => ['twilio' => true]]);
    config(['sms.gateways.twilio' => [
        'account_sid' => 'AC123',
        'auth_token' => 'auth_token_123',
        'from_number' => '+1234567890',
    ]]);

    expect(SmsGatewayFactory::isEnabled('twilio'))->toBeTrue();
});

it('isEnabled returns false for disabled gateway', function () {
    config(['sms.enabled' => ['twilio' => false]]);

    expect(SmsGatewayFactory::isEnabled('twilio'))->toBeFalse();
});

it('getDefault returns first enabled gateway', function () {
    config(['sms.default' => 'twilio']);
    config(['sms.enabled' => ['twilio' => true, 'ssl_wireless' => true]]);
    config(['sms.gateways.twilio' => [
        'account_sid' => 'AC123',
        'auth_token' => 'auth_token_123',
        'from_number' => '+1234567890',
    ]]);
    config(['sms.gateways.ssl_wireless' => [
        'api_token' => 'key123',
        'sid' => 'MyApp',
    ]]);

    $gateway = SmsGatewayFactory::getDefault();

    expect($gateway)->toBeInstanceOf(TwilioGateway::class);
});

it('getDefault returns null when none enabled', function () {
    config(['sms.enabled' => ['twilio' => false, 'ssl_wireless' => false]]);

    expect(SmsGatewayFactory::getDefault())->toBeNull();
});
