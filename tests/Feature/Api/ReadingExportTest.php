<?php

use App\Models\Anemometer;
use App\Models\Reading;

it('requires authentication to export readings', function (): void {
    $response = $this->getJson('/api/readings/export?format=json');

    $response->assertStatus(401);
});

it('exports readings as json by default', function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->create();
    $reading = Reading::factory()->for($anemometer)->withTags(['gusty', 'steady'])->create([
        'speed' => 12.5,
    ]);

    $response = $this->getJson('/api/readings/export');

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $reading->id,
        'speed' => 12.5,
    ]);

    expect(collect($response->json('0.tags'))->sort()->values()->all())
        ->toEqual(['gusty', 'steady']);
});

it('exports readings as csv', function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->create();
    $reading = Reading::factory()->for($anemometer)->withTags(['calm'])->create([
        'speed' => 7.25,
    ]);

    $response = $this->get('/api/readings/export?format=csv');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toContain('id,speed,recorded_at,tags');
    expect($content)->toContain($reading->id);
    expect($content)->toContain('7.25');
    expect($content)->toContain('calm');
});

it('applies existing tag filters to json export', function (): void {
    actingAsUser();
    $anemometer = Anemometer::factory()->create();

    $gustyReading = Reading::factory()->for($anemometer)->withTags(['gusty'])->create();
    $calmReading = Reading::factory()->for($anemometer)->withTags(['calm'])->create();

    $response = $this->getJson('/api/readings/export?format=json&tags_any=gusty');

    $response->assertOk();

    $ids = collect($response->json())->pluck('id')->all();

    expect($ids)->toContain($gustyReading->id);
    expect($ids)->not->toContain($calmReading->id);
});

it('rejects unsupported export formats', function (): void {
    actingAsUser();

    $response = $this->getJson('/api/readings/export?format=xml');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['format']);
});
