<?php

use App\Services\RequestReplies\FeatureEntryParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Each use-case feature's free-text entry becomes the request replies it names (deduped per
 * use case), linked back through feature_request_reply; the feature's function nodes move to
 * its first request reply. Features naming no real request stay entry-less. No flow edges are guessed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parser = new FeatureEntryParser;
        $now = now();

        foreach (DB::table('features')->whereNotNull('use_case_id')->orderBy('id')->get() as $feature) {
            $requestReplyIds = [];

            foreach ($parser->parse($feature->entry, json_decode((string) $feature->triggers, true) ?: []) as $parsed) {
                $identity = ['use_case_id' => $feature->use_case_id, 'method' => $parsed->method, 'entry' => $parsed->entry];
                $requestReplyId = DB::table('request_replies')->where($identity)->value('id')
                    ?? DB::table('request_replies')->insertGetId([
                        ...$identity,
                        'project_id' => $feature->project_id,
                        'trigger' => $parsed->trigger->value,
                        'title' => $feature->title,
                        'module_id' => $feature->module_id,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                DB::table('feature_request_reply')->insertOrIgnore(['feature_id' => $feature->id, 'request_reply_id' => $requestReplyId]);
                $requestReplyIds[] = $requestReplyId;
            }

            if ($requestReplyIds !== []) {
                DB::table('implementation_nodes')
                    ->where('feature_id', $feature->id)
                    ->where('kind', 'function')
                    ->update(['request_reply_id' => $requestReplyIds[0], 'feature_id' => null]);
            }
        }
    }
};
