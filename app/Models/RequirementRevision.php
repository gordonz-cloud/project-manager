<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change of a requirement's statement or decision status; written by Requirement on save, never edited.
 *
 * @property int $id
 * @property int $requirement_id
 * @property string|null $old_statement
 * @property string|null $new_statement
 * @property string|null $old_status
 * @property string|null $new_status
 * @property string|null $reason
 * @property string|null $source
 * @property string|null $decided_by
 * @property Carbon|null $created_at
 */
#[Fillable(['old_statement', 'new_statement', 'old_status', 'new_status', 'reason', 'source', 'decided_by'])]
class RequirementRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function changedStatement(): bool
    {
        return $this->old_statement !== null && $this->old_statement !== $this->new_statement;
    }
}
