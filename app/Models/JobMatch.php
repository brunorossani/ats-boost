<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una oferta encontrada para una búsqueda, con su evaluación y su CV adaptado.
 */
class JobMatch extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_LOW_MATCH = 'low_match';

    public const STATUS_FAILED = 'failed';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'user_id',
        'job_listing_id',
        'document_id',
        'status',
        'match_score',
        'match_details',
        'error',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'match_details' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(JobSearchProfile::class, 'job_search_profile_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithCv(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_READY, self::STATUS_APPLIED])->whereNotNull('document_id');
    }

    public function hasCv(): bool
    {
        return $this->document_id !== null;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_PROCESSING], true);
    }
}
