<?php

use App\Enums\SmsEncoding;
use App\Services\Sms\SmsSegmenter;

beforeEach(function (): void {
    $this->segmenter = new SmsSegmenter;
});

it('bills plain latin text as gsm7', function (): void {
    expect($this->segmenter->encodingFor('Your OTP is 448123. Do not share it.'))
        ->toBe(SmsEncoding::Gsm7);
});

it('bills characters outside the gsm7 alphabet as unicode', function (): void {
    expect($this->segmenter->encodingFor('Your balance is Ȼ40'))->toBe(SmsEncoding::Unicode)
        ->and($this->segmenter->encodingFor('Ваш код 4481'))->toBe(SmsEncoding::Unicode)
        ->and($this->segmenter->encodingFor('Payment received 🎉'))->toBe(SmsEncoding::Unicode);
});

it('fits a gsm7 message of up to 160 septets into one part', function (): void {
    expect($this->segmenter->segmentsFor(str_repeat('a', 160)))->toBe(1)
        ->and($this->segmenter->segmentsFor(str_repeat('a', 161)))->toBe(2)
        ->and($this->segmenter->segmentsFor(str_repeat('a', 306)))->toBe(2)
        ->and($this->segmenter->segmentsFor(str_repeat('a', 307)))->toBe(3);
});

it('counts extended gsm7 characters as two septets', function (): void {
    expect($this->segmenter->unitsFor('€'))->toBe(2)
        ->and($this->segmenter->unitsFor('a€b'))->toBe(4)
        ->and($this->segmenter->segmentsFor(str_repeat('€', 80)))->toBe(1)
        ->and($this->segmenter->segmentsFor(str_repeat('€', 81)))->toBe(2);
});

it('uses shorter limits for unicode messages', function (): void {
    expect($this->segmenter->segmentsFor(str_repeat('Ж', 70)))->toBe(1)
        ->and($this->segmenter->segmentsFor(str_repeat('Ж', 71)))->toBe(2)
        ->and($this->segmenter->segmentsFor(str_repeat('Ж', 134)))->toBe(2)
        ->and($this->segmenter->segmentsFor(str_repeat('Ж', 135)))->toBe(3);
});

it('treats an empty message as a single part', function (): void {
    expect($this->segmenter->segmentsFor(''))->toBe(1);
});

it('reports the remaining space in the final part', function (): void {
    $analysis = $this->segmenter->analyze(str_repeat('a', 170));

    expect($analysis['encoding'])->toBe(SmsEncoding::Gsm7)
        ->and($analysis['units'])->toBe(170)
        ->and($analysis['segments'])->toBe(2)
        ->and($analysis['remaining'])->toBe(136);
});
