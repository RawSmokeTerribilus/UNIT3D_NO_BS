<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisposableEmailDomain extends Model
{
    protected $table = 'disposable_email_domains';

    protected $fillable = [
        'domain',
        'source',
        'description',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->domain = strtolower(trim($model->domain));
        });
    }

    /**
     * Check if a domain is disposable.
     *
     * @param string $domain
     * @return bool
     */
    public static function isDisposable(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        
        return static::where('domain', $domain)->exists();
    }

    /**
     * Get all disposable domains as array for fast lookup.
     *
     * @return array
     */
    public static function getAllDomains(): array
    {
        return static::pluck('domain')->toArray();
    }

    /**
     * Count domains by source.
     *
     * @return array
     */
    public static function countBySource(): array
    {
        return static::groupBy('source')
            ->selectRaw('source, COUNT(*) as count')
            ->pluck('count', 'source')
            ->toArray();
    }
}
