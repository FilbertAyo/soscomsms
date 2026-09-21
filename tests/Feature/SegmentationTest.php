<?php

use App\Models\Customer;
use App\Models\User;
use App\Services\SegmentationService;

function segments(): SegmentationService
{
    return new SegmentationService;
}

it('counts GSM 160 as one segment and 161 as two', function () {
    expect(segments()->analyze(str_repeat('a', 160)))->toMatchArray([
        'encoding' => 'gsm',
        'characterCount' => 160,
        'segmentCount' => 1,
        'singleLimit' => 160,
        'concatLimit' => 153,
    ])->and(segments()->analyze(str_repeat('a', 161)))->toMatchArray([
        'encoding' => 'gsm',
        'characterCount' => 161,
        'segmentCount' => 2,
        'singleLimit' => 160,
        'concatLimit' => 153,
    ]);
});

it('counts Unicode 70 as one segment and 71 as two', function () {
    expect(segments()->analyze(str_repeat('你', 70)))->toMatchArray([
        'encoding' => 'unicode',
        'characterCount' => 70,
        'segmentCount' => 1,
        'singleLimit' => 70,
        'concatLimit' => 63,
    ])->and(segments()->analyze(str_repeat('你', 71)))->toMatchArray([
        'encoding' => 'unicode',
        'characterCount' => 71,
        'segmentCount' => 2,
        'singleLimit' => 70,
        'concatLimit' => 63,
    ]);
});

it('counts GSM extension characters as two septets', function () {
    expect(segments()->analyze('{'))->toMatchArray([
        'encoding' => 'gsm',
        'characterCount' => 2,
        'segmentCount' => 1,
    ])->and(segments()->analyze(str_repeat('a', 159).'{'))->toMatchArray([
        'encoding' => 'gsm',
        'characterCount' => 161,
        'segmentCount' => 2,
    ]);
});

it('keeps Swahili GSM text in GSM and treats an em dash as Unicode', function () {
    expect(segments()->analyze('Habari, ada ya shule inapaswa kulipwa.'))->toMatchArray([
        'encoding' => 'gsm',
        'segmentCount' => 1,
    ])->and(segments()->analyze('Habari—ada ya shule.'))->toMatchArray([
        'encoding' => 'unicode',
        'segmentCount' => 1,
    ]);
});

it('exposes segmentation over the JSON API', function () {
    $user = User::factory()->create();
    Customer::factory()->complete()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/sms/segment', ['message' => str_repeat('a', 161)])
        ->assertOk()
        ->assertJsonPath('encoding', 'gsm')
        ->assertJsonPath('segmentCount', 2);
});
