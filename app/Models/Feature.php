<?php

namespace App\Models;

use App\Enums\FeatureStatus;
use App\Models\Concerns\BelongsToProject;
use App\Models\Concerns\HasProjectSequence;
use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property int $number
 * @property FeatureStatus $status
 * @property array<int, string>|null $triggers
 * @property string|null $entry
 * @property int|null $requirement_id
 * @property string|null $notion_url
 * @property array<int, string>|null $layers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'number', 'status', 'triggers', 'entry', 'requirement_id', 'notion_url', 'layers'])]
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use BelongsToProject, HasFactory, HasProjectSequence;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FeatureStatus::class,
            'triggers' => 'array',
            'layers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    /**
     * @return BelongsToMany<DataModel, $this>
     */
    public function dataModels(): BelongsToMany
    {
        return $this->belongsToMany(DataModel::class);
    }

    /**
     * @return HasMany<FlowStep, $this>
     */
    public function flowSteps(): HasMany
    {
        return $this->hasMany(FlowStep::class);
    }

    /**
     * @return BelongsToMany<Test, $this>
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class);
    }

    /**
     * @return HasMany<Commit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(Commit::class);
    }
}
