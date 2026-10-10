<?php

use App\Services\Commits\RequirementReferenceMatcher;

test('reads every R<number> in subject and body, each once [T132]', function () {
    expect((new RequirementReferenceMatcher)->numbers('R123 R124 修了下单', "顺带 R7\nR123 again"))->toBe([123, 124, 7]);
});

test('ignores R inside words and refs like PR12 or lowercase r3 [T132]', function () {
    expect((new RequirementReferenceMatcher)->numbers('Merge PR12, bump r3, ABC-R4, Feature 5', null))->toBe([]);
});
