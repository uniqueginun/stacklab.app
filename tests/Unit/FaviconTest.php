<?php

use Tests\TestCase;

uses(TestCase::class);

test('the application favicon is the stacklab mark', function () {
    $svg = file_get_contents(public_path('favicon.svg'));

    expect($svg)
        ->not->toContain('#FF2D20')
        ->toContain('#F97015')
        ->toContain('viewBox="0 0 32 32"');

    expect(public_path('favicon.ico'))->toBeFile();

    $appleTouch = getimagesize(public_path('apple-touch-icon.png'));

    expect($appleTouch[0])->toBe(180)
        ->and($appleTouch[1])->toBe(180)
        ->and($appleTouch['mime'])->toBe('image/png');
});
