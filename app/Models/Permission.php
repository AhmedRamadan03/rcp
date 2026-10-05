<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'claims' => 'json',
        'claims_files' => 'json',
        'claim_status' => 'json',
        //'payment_link' => 'json',
        //'reject_claims' => 'json',
        'settings' => 'json',
        'third_parties' => 'json',
        'permissions' => 'json',
        'users' => 'json',
        'transactions' => 'json',
        'reports' => 'json',
        'role' => 'json',
        'payments' => 'json',
        'accounting' => 'json',
        'dashboard' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function userRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Prevent double-encoding: if a JSON-cast field is set with a JSON string,
     * decode it first so the cast doesn't encode it again.
     */
    public function setAttribute($key, $value)
    {
        if (is_string($value) && $this->hasCast($key, ['json', 'array', 'object', 'collection'])) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Ensure a value is always returned as an array.
     * Handles double-encoded legacy data where the cast decodes to a string instead of an array.
     */
    private function ensureArray($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    public function toArray()
    {
        return [
            "id" => $this->id,
            "user_id" => $this->user_id,
            "role_id" => $this->role_id,
            "claims" => $this->ensureArray($this->claims),
            "claims_files" => $this->ensureArray($this->claims_files),
            "claim_status" => $this->ensureArray($this->claim_status),
            //"payment_link" => $this->ensureArray($this->payment_link),
            //"reject_claims" => $this->ensureArray($this->reject_claims),
            "settings" => $this->ensureArray($this->settings),
            "third_parties" => $this->ensureArray($this->third_parties),
            "permissions" => $this->ensureArray($this->permissions),
            "users" => $this->ensureArray($this->users),
            "transactions" => $this->ensureArray($this->transactions),
            "reports" => $this->ensureArray($this->reports),
            "role" => $this->ensureArray($this->getAttributeValue('role')),
            "payments" => $this->ensureArray($this->payments),
            'accounting' => $this->ensureArray($this->getAttributeValue('accounting')),
            'dashboard' => $this->ensureArray($this->getAttributeValue('dashboard')),
        ];
    }
}
