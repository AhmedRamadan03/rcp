<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property int $client_id
 * @property string $api_client_id
 * @property string $client_secret_hash
 * @property string $status  ACTIVE | INACTIVE
 * @property int $created_by
 * @property int|null $revoked_by
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class PartnerApiCredential extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'api_client_id',
        'title',
        'client_id',
        'client_secret_hash',
        'status',
        'created_by',
        'revoked_by',
        'revoked_at',
    ];

    // 5.1: the raw secret is never stored, but the hash itself must
    // also never leak through API responses / array or JSON casting.
    protected $hidden = [
        'client_secret_hash',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }


    public function scopeFilter($query, $filters)
    {
        if (isset($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('api_client_id', 'ILIKE', "%{$search}%")
                ->orWhereHas('createdBy', function ($sub) use ($search) {
                    $sub->where('name', 'ILIKE', "%{$search}%");
                })
                ->orWhereHas('revokedBy', function ($sub) use ($search) {
                    $sub->where('name', 'ILIKE', "%{$search}%");
                });
            });
        }

        if (isset($filters['status'])) {
            $query->whereIn('status', Arr::wrap($filters['status']));
        }
        
        if (isset($filters['api_client_id'])) {
            $query->where('api_client_id','ILIKE', '%' . $filters['api_client_id'] . '%');
        }

        return $query;
    }
}