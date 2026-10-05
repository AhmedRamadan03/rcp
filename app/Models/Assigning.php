<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Assigning extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function supervisorUser()
    {
        return $this->belongsTo(User::class, 'supervisor_id')->withTrashed();
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from')->withTrashed();
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to')->withTrashed();
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }


    public function scopeFilter($query, $filters)
    {
        if( isset($filters['user'])){
            $query->where(function ($q) use ($filters) {
                $q->whereHas('fromUser', function ($q) use ($filters){
                    $q->where('name', 'ILIKE', '%' . $filters['user'] . '%');
                })
                ->orWhereHas('toUser', function ($q) use ($filters){
                    $q->where('name', 'ILIKE', '%' . $filters['user'] . '%');
                });
            });
            
        }
        if( isset($filters['search'])){
            $search = '%' . $filters['search'] . '%';
            $query->leftJoin('users as from_user_search', 'from_user_search.id', '=', 'assignings.from')
              ->leftJoin('users as to_user_search', 'to_user_search.id', '=', 'assignings.to')
              ->leftJoin('users as supervisor_search', 'supervisor_search.id', '=', 'assignings.supervisor_id')
              ->leftJoin('claims as claim_search', 'claim_search.id', '=', 'assignings.claim_id')
              ->where(function ($q) use ($search) {
                  $q->where('from_user_search.name', 'ILIKE', $search)
                    ->orWhere('to_user_search.name', 'ILIKE', $search)
                    ->orWhere('supervisor_search.name', 'ILIKE', $search)
                    ->orWhere('claim_search.claim_number', 'ILIKE', $search);
              })
              ->select('assignings.*');
            
        }
        if(isset($filters['supervisor'])){
            $query->whereHas('supervisorUser', function ($q) use ($filters){
                $q->where('name', 'ILIKE', '%' . $filters['supervisor'] . '%');
            });
        }
        if(isset($filters['from_user'])){
            $query->whereHas('fromUser', function ($q) use ($filters){
                $q->where('name', 'ILIKE', '%' . $filters['from_user'] . '%');
            });
        }
        if(isset($filters['to_user'])){
            $query->whereHas('toUser', function ($q) use ($filters){
                $q->where('name', 'ILIKE', '%' . $filters['to_user'] . '%');
            });
        }
        if(isset($filters['claim_number'])){
            $query->whereHas('claim', function ($q) use ($filters){
                $q->where('claim_number', 'ILIKE', '%' . $filters['claim_number'] . '%');
            });
        }
        if (isset($filters['assign_date_from']) && isset($filters['assign_date_to'])) {
            $query->whereBetween('assign_date', [$filters['assign_date_from'], $filters['assign_date_to']]);
        }
        if (isset($filters['created_at_from']) && isset($filters['created_at_to'])) {
            $query->whereBetween('created_at', [$filters['created_at_from'], $filters['created_at_to']]);
        }

        if (isset($filters['status'])) {
            $query->whereIn('is_active', Arr::wrap($filters['status']));
        }
        if (isset($filters['ids'])) {
            $query->whereIn('id', Arr::wrap($filters['ids']));
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'claim_id' => $this->claim_id,
            'from' => $this->fromUser?->name ?? null,
            'to' => $this->toUser->name,
            'from_id' => $this->from,
            'to_id' => $this->to,
            'supervisor' => $this->supervisorUser?->name ?? null,
            'assign_date' => $this->assign_date,
            'assign_comment' => $this->assign_comment
        ];
    }
}
