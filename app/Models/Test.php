<?php

namespace App\Models;

use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\TestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $location
 * @property TestStatus $status
 * @property TestLastResult $last_result
 * @property string|null $notion_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'location', 'status', 'last_result', 'notion_url'])]
class Test extends Model
{
    /** @use HasFactory<TestFactory> */
    use BelongsToProject, HasFactory;

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
}
