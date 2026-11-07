<?php

use App\Models\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows the English privacy policy by default with an Arabic language option', function () {
    $this->get('/privacy-policy')
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr">', false)
        ->assertSee('id="policy-en" data-policy-language="en"', false)
        ->assertSee('id="policy-ar" data-policy-language="ar"', false)
        ->assertSee('<option value="en" selected>English</option>', false)
        ->assertSee('Privacy Policy')
        ->assertSee('سياسة الخصوصية')
        ->assertSee(asset('images/main-logo.png'), false)
        ->assertDontSee('[privacy@yourdomain.com]');
});

it('shows the published support phone and does not expose the store address', function () {
    StoreSettings::query()->create([
        'store_name_ar' => 'أرواق العنب',
        'store_name_en' => 'Arwaq Al-Eneab',
        'support_phone' => '+966500000000',
        'address' => '<script>alert(1)</script>',
    ]);

    $this->get('/privacy-policy')
        ->assertOk()
        ->assertSee('+966530697231')
        ->assertSee('href="tel:+966530697231"', false)
        ->assertDontSee('+966500000000')
        ->assertDontSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});
