<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ClaimStatus;
use App\Enums\Roles;
use App\Http\Resources\Api\V1\Dashboard\UserTargetResource;
use App\Http\Resources\Api\V1\Users\UserSettingResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $with = ['client', 'role', 'firstPermission'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_image',
        'profile_image_file_id',
        'client_id',
        'role_id',
        'phone',
        'address',
        'bio',
        'supervisor_id',
        'first_time_login',
        'discount_percentage',
        'has_import_in_progress',
        'user_type',
        'last_active_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_active_at' => 'datetime',
        'password' => 'hashed',
        'discount_percentage' => 'float',
    ];



    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function profileImage()
    {
        return $this->belongsTo(File::class, 'profile_image_file_id');
    }

    /**
     * Resolve the URL the frontend should use for this user's avatar.
     *
     * Delegates to File::resolveUrl() when a File row is associated, so the
     * public/private and GCS/local logic is handled in one place. Falls back
     * to the legacy show-file route for rows that predate the files registry.
     */
    public function getProfileImageUrlAttribute(): ?string
    {
        $file = $this->profileImage;

        if ($file) {
            return $file->resolveUrl();
        }

        // Legacy rows without a File FK — serve through show-file via the
        // legacy-path fallback in FileController::serve().
        if (filled($this->profile_image)) {
            return route('public.file', ['key' => ltrim($this->profile_image, '/')]);
        }

        return null;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function claims()
    {
        return $this->hasMany(Claim::class);
    }

    public function permission(): HasMany|array
    {
        return $this->hasMany(Permission::class, 'user_id', 'id')->latest();
    }

    public function firstPermission(): HasOne|array
    {
        return $this->hasOne(Permission::class, 'user_id', 'id')->latest();
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id', 'id');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'supervisor_id', 'id');
    }

    public function delegationsFrom()
    {
        return $this->hasMany(Delegation::class, 'from', 'id');
    }

    public function delegationsTo()
    {
        return $this->hasMany(Delegation::class, 'to', 'id');
    }

    public function delegationTo()
    {
        return $this->hasMany(Delegation::class, 'to', 'id')->where('is_active', 1);
    }

    public function delegationFrom()
    {
        return $this->hasMany(Delegation::class, 'from', 'id')->where('is_active', 1);
    }

    public function targets()
    {
        return $this->hasMany(Target::class, 'user_id', 'id');
    }
    public function historyTargets()
    {
        return $this->hasMany(TargetHistory::class, 'user_id', 'id');
    }

    public function currentTarget()
    {
        $today = Carbon::today()->format('Y-m-d');

        return $this->hasOne(Target::class)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->latest();
    }
    public function previousTarget()
    {
        $today = Carbon::today()->format('Y-m-d');

        return $this->hasOne(Target::class)
            ->where('end_date', '<', $today)
            ->orderBy('end_date', 'desc'); // آخر Target انتهى
    }


//     public function currentTarget()
//     {
//         return $this->hasOne(Target::class)
// //            ->where('start_date', Carbon::today()->firstOfMonth()->format('Y-m-d'))
//             ->whereHas('period', function ($query) {
//                 $query->where('is_active', 1);
//             })->latest();

//     }

    // public function previousTarget()
    // {
    //     return $this->hasOne(Target::class)
    //         ->whereHas('period', function ($query) {
    //             $query->where('is_active', 0);
    //         })->latest();

    // }

    public function assinedTo()
    {
        return $this->hasOne(Assigning::class, 'to', 'id');
    }

    public function officer_claims()
    {
        return $this->hasManyThrough(Claim::class, Assigning::class, 'to', 'id', 'id', 'claim_id')
            ->where('is_active', 1);
    }

    public function outstanding_status()
    {
        return $this->officer_claims()
            ->where('status', ClaimStatus::REGISTERED->value);
    }

    public function pending_status()
    {
        return $this->officer_claims()
            ->where('status', ClaimStatus::APPROVED->value);
    }

    public function remaining_claims()
    {
        return $this->officer_claims()
            ->whereNotIn('status', [ClaimStatus::MANUAL_COLLECTED->value, ClaimStatus::DONE->value, ClaimStatus::CREDITED->value]);
    }

    public function collected_claims()
    {
        return $this->officer_claims()
            ->whereIn('status', [ClaimStatus::MANUAL_COLLECTED->value, ClaimStatus::DONE->value, ClaimStatus::CREDITED->value]);
    }

    public function canceled_claims()
    {
        return $this->officer_claims()
            ->where('status', ClaimStatus::REJECTED->value);
    }

    public function closed_status()
    {
        return $this->officer_claims()
            ->where('status', ClaimStatus::CLOSED->value);
    }

    public function delay_status()
    {
        return $this->officer_claims()
            ->where('status', ClaimStatus::DELAY->value);
    }

    public function partial_status()
    {
        return $this->officer_claims()
            ->where('status', ClaimStatus::PARTIAL->value);
    }

    public function legal_status()
    {
        return $this->officer_claims()
            ->where('status', ClaimStatus::LAW_FIRM->value);
    }

    public function follow_up_status()
    {
        return $this->officer_claims()
            ->where('status', ClaimStatus::FOLLOW_UP->value);
    }

    public function authenticationLogs(): MorphMany
    {
        return $this->morphMany(AuthenticationLog::class, 'authenticatable');
    }


       /**
     * Get last active at from authentication logs
     */
    // public function getLastActiveAtAttribute()
    // {
    //     $lastLogin = $this->authenticationLogs()
    //         ->where('login_at', '!=', null)
    //         ->latest('login_at')
    //         ->first();

    //     return $lastLogin ? $lastLogin->login_at : $this->created_at;
    // }


    public function getEffectiveDiscountPercentageAttribute()
    {
        return $this->discount_percentage ?? $this->role?->discount_percentage;
    }

    public function userSettings()
    {
        return $this->hasMany(UserSetting::class);
    }


    public function scopeFilter($query, $filters,$user = null)
    {
        $user = auth()->user()?? $user;
        $isSeniorCollector = $user->role_id == Roles::SENIOR_COLLECTOR->value ?? false;

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            });
        }
        if (isset($filters['name'])) {
            $name = $filters['name'];
            $query->where(function ($q) use ($name) {
                $q->where('name', 'ILIKE', "%{$name}%")
                    ->orWhere('email', 'ILIKE', "%{$name}%");
                   
            });
        }
        if (isset($filters['phone'])) {
            $phone = $filters['phone'];
            $query->where('phone', 'ILIKE', "%{$phone}%");
           
        }
        if (isset($filters['last_active_from']) && isset($filters['last_active_to']) ) {
                $query->whereBetween('last_active_at', [Carbon::parse($filters['last_active_from'])->startOfDay(),Carbon::parse($filters['last_active_to'])->endOfDay()]);
        }
        if (isset($filters['created_at_from']) && isset($filters['created_at_to']) ) {
                $query->whereBetween('created_at', [Carbon::parse($filters['created_at_from'])->startOfDay(),Carbon::parse($filters['created_at_to'])->endOfDay()]);
        }
        if (isset($filters['supervisor'])) {
            $supervisor = $filters['supervisor'];
            $query->whereHas('supervisor', function ($q) use ($supervisor) {
                $q->where('name', 'ILIKE', "%{$supervisor}%");
                   
            });
        }


        if (!empty($filters['user_type'])) {
            $query->where('user_type', $filters['user_type']);
        }

        if (!empty($filters['role_id'])) {
            $roleIds = explode(',', $filters['role_id']);
            $roleIds = array_filter(array_map('intval', $roleIds));

            if ($isSeniorCollector) {
                $roleIds[] = Roles::SENIOR_COLLECTOR->value;
            }

            if (!empty($roleIds)) {
                $query->whereIn('role_id', $roleIds);
            }
        }

        if ($isSeniorCollector) {
            $query->where('supervisor_id', auth()->user()->id);
        }
        
    }


    public function toArray(): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "email" => $this->email,
            "profile_image" => $this->profile_image_url,
            "phone" => $this->phone,
            "user_type" => $this->user_type,
            "created_at" => $this->created_at,
            "last_active_at" => Carbon::parse($this->last_active_at)->format('Y-m-d H:i:s'),
            "role" => $this->role->title ?? '',
            "role_id" => $this->role_id,
            "supervisor" => $this->supervisor?->name,
            "user_discount_percentage" => $this->discount_percentage,
            "role_discount_percentage" => $this->role->discount_percentage,
            "active" => !$this->trashed(),
            "permissions" => $this->firstPermission,
            "settings" => new UserSettingResource($this),
            'target' => new UserTargetResource($this),
        ];

    }
}
