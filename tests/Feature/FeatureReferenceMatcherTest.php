<?php

use App\Models\Feature;
use App\Services\Commits\FeatureReferenceMatcher;
use Illuminate\Support\Collection;

function matchFeatureReference(string $subject, ?string $body, Collection $featuresByNumber): ?Feature
{
    return (new FeatureReferenceMatcher)->match($subject, $body, $featuresByNumber);
}

test('links an explicit Feature N reference', function () {
    $feature12 = Feature::factory()->create(['number' => 12]);

    $result = matchFeatureReference('Feature 12', null, collect([12 => $feature12]));

    expect($result->is($feature12))->toBeTrue();
});

test('does not link Chinese 功能 N or an N:N ratio after the word feature', function () {
    $feature1 = Feature::factory()->create(['number' => 1]);
    $features = collect([1 => $feature1]);

    expect(matchFeatureReference('功能 1:1', null, $features))->toBeNull()
        ->and(matchFeatureReference('feature 1:1 mapping', null, $features))->toBeNull();
});

test('links the first of multiple Feature N references, as before', function () {
    $feature12 = Feature::factory()->create(['number' => 12]);
    $feature13 = Feature::factory()->create(['number' => 13]);
    $features = collect([12 => $feature12, 13 => $feature13]);

    $result = matchFeatureReference('Feature 12, Feature 13', null, $features);

    expect($result->is($feature12))->toBeTrue();
});
