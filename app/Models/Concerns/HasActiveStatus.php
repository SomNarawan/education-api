<?php

namespace App\Models\Concerns;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Builder;

trait HasActiveStatus
{
    protected static function bootHasActiveStatus(): void
    {
        static::addGlobalScope(
            'active',
            fn (Builder $query) => $query->where(
                $query->getModel()->qualifyColumn('status'),
                Status::ACTIVE
            )
        );
    }

    public function scopeWithInactive(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active');
    }
}
