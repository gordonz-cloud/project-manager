<?php

use App\Enums\FeatureTrigger;
use App\Models\Feature;
use App\Models\RequestReply;
use App\Models\RequestReplyEdge;
use App\Models\UseCase;
use App\Services\RequestReplies\FeatureEntryParser;

test('a flow edge joins two different request replies of one use case', function () {
    $useCase = UseCase::factory()->create();
    [$login, $home] = RequestReply::factory()->count(2)->create(['use_case_id' => $useCase->id]);
    $foreign = RequestReply::factory()->create();

    $edge = RequestReplyEdge::factory()->create(['from_request_reply_id' => $login->id, 'to_request_reply_id' => $home->id]);

    expect($edge->project_id)->toBe($useCase->project_id)
        ->and(fn () => RequestReplyEdge::factory()->create(['from_request_reply_id' => $login->id, 'to_request_reply_id' => $login->id]))
        ->toThrow(LogicException::class, 'cannot depend on itself')
        ->and(fn () => RequestReplyEdge::factory()->create(['from_request_reply_id' => $login->id, 'to_request_reply_id' => $foreign->id]))
        ->toThrow(LogicException::class, 'same use case');
});

test('a feature links only request replies of its own project', function () {
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
