<?php

use App\Support\Pem;

test('it rewrites windows pem for nginx', function () {
    $body = str_repeat('A', 80);
    $messy = "-----BEGIN CERTIFICATE-----\r\n{$body}\r\n-----END CERTIFICATE-----";

    $normalized = Pem::normalizeCertificates($messy);

    expect($normalized)->not->toBeNull()
        ->and($normalized)->not->toContain("\r")
        ->and($normalized)->toStartWith("-----BEGIN CERTIFICATE-----\n")
        ->and($normalized)->toEndWith("-----END CERTIFICATE-----\n")
        ->and($normalized)->toContain("\n".str_repeat('A', 64)."\n".str_repeat('A', 16)."\n");
});

test('it inserts a newline between concatenated certificates', function () {
    $leaf = "-----BEGIN CERTIFICATE-----\nAAAA\n-----END CERTIFICATE-----";
    $chain = "-----BEGIN CERTIFICATE-----\nBBBB\n-----END CERTIFICATE-----";

    $normalized = Pem::normalizeCertificates($leaf.$chain);

    expect($normalized)->toBe(
        "-----BEGIN CERTIFICATE-----\nAAAA\n-----END CERTIFICATE-----\n-----BEGIN CERTIFICATE-----\nBBBB\n-----END CERTIFICATE-----\n"
    );
});

test('it converts trusted certificate labels for nginx', function () {
    $input = "-----BEGIN TRUSTED CERTIFICATE-----\nAAAA\n-----END TRUSTED CERTIFICATE-----\n";

    expect(Pem::normalizeCertificates($input))->toBe(
        "-----BEGIN CERTIFICATE-----\nAAAA\n-----END CERTIFICATE-----\n"
    );
});

test('it rejects non certificate text', function () {
    expect(Pem::isCertificate('not a cert'))->toBeFalse()
        ->and(Pem::normalizeCertificates('not a cert'))->toBeNull();
});

test('it rewrites private keys and strips carriage returns', function () {
    $messy = "-----BEGIN PRIVATE KEY-----\r\nMIIBprivate\r\n-----END PRIVATE KEY-----";

    expect(Pem::isPrivateKey($messy))->toBeTrue()
        ->and(Pem::normalizePrivateKey($messy))->toBe(
            "-----BEGIN PRIVATE KEY-----\nMIIBprivate\n-----END PRIVATE KEY-----\n"
        );
});
