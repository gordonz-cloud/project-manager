<?php

use App\Enums\FeatureTrigger;
use App\Enums\ImplementationNodeKind;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\RequestReply;
use App\Models\RequestReplyEdge;
use App\Models\Scenario;
use App\Models\ScenarioStep;
use App\Models\UseCase;
use App\Services\RequestReplies\FeatureEntryParser;
use Illuminate\Support\Facades\DB;

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

test('a scenario step must be a request reply of the scenario use case', function () {
    $scenario = Scenario::factory()->create();
    $own = RequestReply::factory()->create(['use_case_id' => $scenario->use_case_id]);
    $foreign = RequestReply::factory()->create();

    ScenarioStep::query()->create(['scenario_id' => $scenario->id, 'position' => 1, 'request_reply_id' => $own->id]);

    expect($scenario->steps()->pluck('request_reply_id')->all())->toBe([$own->id])
        ->and(fn () => ScenarioStep::query()->create(['scenario_id' => $scenario->id, 'position' => 2, 'request_reply_id' => $foreign->id]))
        ->toThrow(LogicException::class, 'scenario use case');
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

test('the migration turns feature entries into request replies and moves call trees', function () {
    $useCase = UseCase::factory()->create();
    $checkout = Feature::factory()->forUseCase($useCase)->create(['entry' => 'POST /cart/checkout；GET /checkout/success', 'triggers' => ['HTTP']]);
    $again = Feature::factory()->forUseCase($useCase)->create(['entry' => 'POST /cart/checkout 改文案']);
    $visual = Feature::factory()->forUseCase($useCase)->create(['entry' => '修：登录页溢出']);
    $node = ImplementationNode::factory()->create(['project_id' => $useCase->project_id, 'feature_id' => $checkout->id]);
    DB::table('implementation_nodes')->where('id', $node->id)->update(['kind' => ImplementationNodeKind::Function->value]);
    $design = ImplementationNode::factory()->create(['project_id' => $useCase->project_id, 'feature_id' => $checkout->id]);

    (require base_path('database/migrations/2026_09_23_025808_move_feature_entries_into_request_replies.php'))->up();

    $post = RequestReply::query()->where('method', 'POST')->sole();

    expect(RequestReply::query()->orderBy('id')->pluck('entry')->all())->toBe(['/cart/checkout', '/checkout/success'])
        ->and($post->title)->toBe($checkout->title)
        ->and($post->features()->orderBy('features.id')->pluck('features.id')->all())->toBe([$checkout->id, $again->id])
        ->and($visual->requestReplies()->exists())->toBeFalse()
        ->and($node->fresh()->request_reply_id)->toBe($post->id)
        ->and($node->fresh()->feature_id)->toBeNull()
        ->and($design->fresh()->request_reply_id)->toBeNull()
        ->and(RequestReplyEdge::query()->exists())->toBeFalse();
});
