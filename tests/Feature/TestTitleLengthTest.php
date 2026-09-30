<?php

use App\Models\Test;

test('test title round-trips at 300 characters', function () {
    $title = str_repeat('a', 300);

    $test = Test::factory()->create(['title' => $title]);

    expect($test->fresh()->title)->toBe($title);
});
