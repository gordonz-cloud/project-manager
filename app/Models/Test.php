<?php

namespace App\Models;

use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Enums\TestPriority;
use App\Enums\TestStatus;
use App\Models\Concerns\BelongsToProject;
use App\Models\Concerns\HasProjectSequence;
use Database\Factories\TestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One Test Matrix node: one atomic action (title) and its expected result.
 * parent_id points at the step that must be done first; root to leaf is one test case.
 *
 * @property int $id
 * @property int $project_id
 * @property int $number
 * @property int|null $parent_id
 * @property string|null $module
 * @property string $title
 * @property string|null $expected
 * @property TestPriority|null $priority
 * @property string|null $platform
 * @property string|null $location
 * @property string|null $test_name
 * @property TestAuto|null $auto
 * @property TestStatus $status
 * @property TestLastResult $last_result
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'parent_id', 'module', 'title', 'expected', 'priority', 'platform', 'location', 'test_name', 'auto', 'status', 'last_result', 'notes'])]
class Test extends Model
{
    /** @use HasFactory<TestFactory> */
    use BelongsToProject, HasFactory, HasProjectSequence;

    protected static function booted(): void
    {
        static::saving(function (self $test): void {
            $test->guardParent();
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
            'priority' => TestPriority::class,
            'auto' => TestAuto::class,
        ];
    }

    /**
     * @return BelongsTo<Test, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Test, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Rules this test node verifies.
     *
     * @return BelongsToMany<Requirement, $this>
     */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class);
    }

    /**
     * Business area this node is grouped under; no module means 未分类.
     */
    public function area(): string
    {
        return filled($this->module) ? $this->module : '未分类';
    }

    public function lacksEvidence(): bool
    {
        return blank($this->location);
    }

    /**
     * Meant to be automated but no test file backs it yet.
     */
    public function isGap(): bool
    {
        return $this->lacksEvidence() && in_array($this->auto, [TestAuto::Yes, TestAuto::Partial], true);
    }

    /**
     * The parent must exist in the same project and must not be this node or one of its descendants.
     */
    private function guardParent(): void
    {
        $parentId = $this->parent_id;
        $seen = [];

        while ($parentId !== null) {
            if ($parentId === $this->id || isset($seen[$parentId])) {
                throw new LogicException("Test #{$this->number}: parent chain loops back on itself.");
            }

            $seen[$parentId] = true;
            $parent = self::withoutGlobalScopes()->find($parentId, ['id', 'project_id', 'parent_id']);

            if ($parent === null || $parent->project_id !== $this->project_id) {
                throw new LogicException("Test #{$this->number}: parent must be a test in the same project.");
            }

            $parentId = $parent->parent_id;
        }
    }
}
