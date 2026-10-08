<?php

use App\Enums\FeatureTrigger;
use App\Models\Feature;
use App\Models\RequestReply;
use App\Services\RequestReplies\FeatureEntryParser;

test('a feature links only request replies of its own project [T10]', function () {
    $requestReply = RequestReply::factory()->create();
    $own = Feature::factory()->create(['project_id' => $requestReply->project_id]);
    $foreign = Feature::factory()->create();

    $own->requestReplies()->attach($requestReply);

    expect($requestReply->features()->orderBy('features.id')->pluck('features.id')->all())->toBe([$own->id])
        ->and(fn () => $foreign->requestReplies()->attach($requestReply))
        ->toThrow(LogicException::class, 'same project');
});

test('the parser finds routes, artisan signatures and command classes in free text', function (string $entry, array $triggers, array $expected) {
    $parsed = array_map(
        fn ($found) => [$found->trigger, $found->title()],
        (new FeatureEntryParser)->parse($entry, $triggers),
    );

    expect($parsed)->toBe($expected);
})->with([
    'escaped route' => ['POST /member/orders/\{order\}/return-request；表', [], [[FeatureTrigger::Http, 'POST /member/orders/{order}/return-request']]],
    'webhook' => ['POST /webhooks/stripe: refund.created', [], [[FeatureTrigger::Webhook, 'POST /webhooks/stripe']]],
    'two routes, dedupe' => ['GET /cart/checkout; GET /cart/checkout；DELETE member/saved-items）', [], [[FeatureTrigger::Http, 'GET /cart/checkout'], [FeatureTrigger::Http, 'DELETE /member/saved-items']]],
    'elided path skipped' => ['POST .../revoke', [], []],
    'scheduled artisan' => ['php artisan inventory:reconcile；php artisan migrate', ['Scheduler'], [[FeatureTrigger::Scheduler, 'inventory:reconcile']]],
    'command class' => ['命令 GrantRole', ['CLI'], [[FeatureTrigger::Cli, 'GrantRole']]],
    'ui work' => ['修：登录页溢出', ['UI'], []],
]);
