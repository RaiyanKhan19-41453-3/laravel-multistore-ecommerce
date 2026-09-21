<?php

use App\Services\Zatca\ZatcaQrService;

it('encodes exact phase 1 tlv vector', function () {
    $payload = (new ZatcaQrService)->phaseOnePayload(
        'A', '1', '2026-01-01T00:00:00Z', '115.00', '15.00'
    );

    expect($payload)->toBe('AQFBAgExAxQyMDI2LTAxLTAxVDAwOjAwOjAwWgQGMTE1LjAwBQUxNS4wMA==');
});

it('decodes back to the five ordered tags', function () {
    $payload = (new ZatcaQrService)->phaseOnePayload(
        'شركة المثال', '300012345600003', '2026-03-25T14:30:00Z', '1150.00', '150.00'
    );

    $raw = base64_decode($payload);
    $tags = [];
    $pos = 0;

    while ($pos < strlen($raw)) {
        $tag = ord($raw[$pos]);
        $len = ord($raw[$pos + 1]);
        $tags[$tag] = substr($raw, $pos + 2, $len);
        $pos += 2 + $len;
    }

    expect(array_keys($tags))->toBe([1, 2, 3, 4, 5]);
    expect($tags[1])->toBe('شركة المثال');
    expect($tags[2])->toBe('300012345600003');
    expect($tags[3])->toBe('2026-03-25T14:30:00Z');
    expect($tags[4])->toBe('1150.00');
    expect($tags[5])->toBe('150.00');
});

it('measures length in bytes for multibyte values', function () {
    $raw = base64_decode((new ZatcaQrService)->base64([1 => 'شركة المثال']));

    // 11 characters but 21 UTF-8 bytes: ZATCA requires byte length.
    expect(ord($raw[1]))->toBe(21);
});

it('rejects out-of-range tags and oversized values', function () {
    $service = new ZatcaQrService;

    expect(fn () => $service->tlv([0 => 'x']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $service->tlv([1 => str_repeat('x', 256)]))->toThrow(InvalidArgumentException::class);
});

it('renders scannable svg qr output', function () {
    $service = new ZatcaQrService;
    $payload = $service->phaseOnePayload('A', '1', '2026-01-01T00:00:00Z', '115.00', '15.00');

    $svg = $service->svg($payload);

    expect($svg)->toContain('<svg');
    expect($svg)->toContain('</svg>');
});
