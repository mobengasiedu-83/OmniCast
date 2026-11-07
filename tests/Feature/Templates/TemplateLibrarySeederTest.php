<?php

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use App\Models\MessageTemplate;
use App\Models\TemplateCategory;
use Database\Seeders\TemplateLibrarySeeder;

it('seeds fifty starting points per channel into ten categories', function (): void {
    $this->seed(TemplateLibrarySeeder::class);

    $categories = TemplateCategory::query()->whereNull('client_id')->count();
    $sms = MessageTemplate::query()->where('channel', Channel::Sms->value)->count();
    $email = MessageTemplate::query()->where('channel', Channel::Email->value)->count();
    $active = MessageTemplate::query()
        ->whereNull('client_id')
        ->where('status', TemplateStatus::Active->value)
        ->count();

    expect($categories)->toBe(10)
        ->and($sms)->toBe(50)
        ->and($email)->toBe(50)
        ->and($active)->toBe(100);
});

it('files every email template under a category with a subject line', function (): void {
    $this->seed(TemplateLibrarySeeder::class);

    $templates = MessageTemplate::query()->where('channel', Channel::Email->value)->get();

    expect($templates->whereNull('subject'))->toBeEmpty()
        ->and($templates->whereNull('template_category_id'))->toBeEmpty()
        ->and($templates->pluck('name')->unique()->count())->toBe(50);
});

it('signs every email off and adds the marketing footer only where it is required', function (): void {
    $this->seed(TemplateLibrarySeeder::class);

    $marketing = MessageTemplate::query()
        ->where('channel', Channel::Email->value)
        ->where('name', 'Special Discount')
        ->sole();

    $transactional = MessageTemplate::query()
        ->where('channel', Channel::Email->value)
        ->where('name', 'Payment Confirmation')
        ->sole();

    expect($marketing->subject)->toBe('Your exclusive {{discount}}% offer from {{company_name}}')
        ->and($marketing->body)->toContain('<p>Dear {{first_name}},</p>')
        ->and($marketing->body)->toContain('<a href="{{cta_link}}">Shop the offer</a>')
        ->and($marketing->body)->toContain('Kind regards,<br>{{company_name}}<br>{{support_email}} | {{support_number}}')
        ->and($marketing->body)->toContain('<a href="{{unsubscribe_link}}">Unsubscribe</a>')
        ->and($marketing->variableTags())->toContain('{{cta_link}}')
        ->and($marketing->variableTags())->toContain('{{unsubscribe_link}}');

    expect($transactional->body)->not->toContain('unsubscribe_link');
});

it('does not duplicate the catalogue when it is seeded twice', function (): void {
    $this->seed(TemplateLibrarySeeder::class);

    $this->seed(TemplateLibrarySeeder::class);

    expect(MessageTemplate::query()->count())->toBe(100)
        ->and(TemplateCategory::query()->count())->toBe(10);
});

it('fills in the placeholders of a catalogue entry that was published without them', function (): void {
    $published = MessageTemplate::factory()
        ->channel(Channel::Email)
        ->create(['name' => 'Special Discount', 'subject' => null, 'body' => '<p>Hello {{first_name}}, {{reward_details}}</p>']);

    $published->forceFill(['variables' => null])->saveQuietly();

    $this->seed(TemplateLibrarySeeder::class);

    $published->refresh();

    $kept = MessageTemplate::query()
        ->where('channel', Channel::Email->value)
        ->where('name', 'Special Discount')
        ->get();

    expect($kept)->toHaveCount(1)
        ->and($published->variableTags())->toBe(['{{first_name}}', '{{reward_details}}']);
});
