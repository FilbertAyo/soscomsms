<?php

use App\Services\Gateway\MobishastraBalanceParser;

it('reads Tanzania credits and does not concatenate the +255 country code', function (string $raw) {
    expect(MobishastraBalanceParser::parse($raw))->toBe(5009);
})->with([
    'Tanzania(+255) = 5009',
    'Tanzania(+255)=5009',
    'Tanzania (+255) : 5009',
    '+255=5009',
    '255=5009',
    '255,5009',
    "India(+91) = 100\nTanzania(+255) = 5009\nUAE(+971) = 20",
    '<div>Tanzania(+255) = 5009</div>',
    '5009',
    '5009 SMS',
]);

it('does not invent a balance from an error or empty body', function (?string $raw) {
    expect(MobishastraBalanceParser::parse((string) $raw))->toBeNull();
})->with([
    'Invalid Password',
    'Invalid Profile Id',
    '',
    'Authorization failed',
]);
