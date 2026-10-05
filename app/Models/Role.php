<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Role extends Model
{
    use HasFactory;

    protected $casts = [
        'discount_percentage' => 'float',
    ];
    protected $guarded = ['id'];

    public function users()
    {
        return $this->hasMany(User::class, 'role_id', 'id');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'role_id', 'id');
    }


    public function scopeFilter($query, $filters)
    {
        if (isset($filters['title'])) {
            $query->where('title', 'ILIKE', '%' . $filters['title'] . '%');
        }

        if (isset($filters['description'])) {
            $query->where('description', 'ILIKE', '%' . $filters['description'] . '%');
        }

        if (isset($filters['users_count'])) {
            $query->whereRaw(
                '(select count(*) from "users" where "users"."role_id" = "roles"."id" and "users"."deleted_at" is null) = ?',
                [$filters['users_count']]
            );
        }

        if (isset($filters['created_at_from']) && isset($filters['created_at_to'])) {
            $query->whereBetween('created_at', [Carbon::parse($filters['created_at_from'])->startOfDay(),Carbon::parse($filters['created_at_to'])->endOfDay()]);
        }

      
        

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'ILIKE', $searchTerm)
                    ->orWhere('description', 'ILIKE', $searchTerm)
                    ->orWhere('discount_percentage', 'ILIKE', $searchTerm);
            });
        }

        if (isset($filters['discount'])) {
            $query->where('discount_percentage', '=', $filters['discount']);
        }
        if (isset($filters['ids'])) {
            $query->whereIn('id', Arr::wrap($filters['ids']));
        }
    }

    public function toArray()
    {
        $permissions = json_decode($this->default_permissions, true);

        if ($permissions) {
            foreach ($permissions as $key =>$permission) {
                $permissions[$key] = json_decode($permission, true);
            }
        }


        return [
            "id" => $this->id,
            "title" => $this->title,
            "description" => $this->description,
            "discount_percentage" => $this->discount_percentage,
            "users_assigned"=> $this->users()->count(),
            "default_permissions" => $permissions,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "deleted_at" => $this->deleted_at,

        ];
    }
}
