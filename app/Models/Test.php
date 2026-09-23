<?php

namespace App\Models;

use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\TestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $scenario_id
 * @property string $title
 * @property string|null $location
 * @property TestStatus $status
 * @property TestLastResult $last_result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['scenario_id', 'title', 'location', 'status', 'last_result'])]
class Test extends Model
{
    /** @use HasFactory<TestFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $test): void {
            if ($test->scenario_id === null) {
                return;
            }

            $scenario = Scenario::withoutGlobalScopes()->find($test->scenario_id);

            if ($scenario === null) {
                throw new LogicException('A test scenario must exist.');
            }

            $projectId = $test->getRawOriginal('project_id');

            if ($projectId === null) {
                $test->project_id = $scenario->project_id;
            } elseif ((int) $projectId !== $scenario->project_id) {
                throw new LogicException('A test scenario must belong to the same project.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TestStatus::class,
            'last_result' => TestLastResult::class,
        ];
    }

    /**
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    /**
     * @return BelongsTo<Scenario, $this>
     */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}
