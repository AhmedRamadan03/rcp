<?php

namespace App\Models;

use App\Enums\DataHistoryEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Override;

class DataHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'ref',
        'title',
        'type',
        'status',
        'file_path',
        'notification_id',
        'filters',
    ];



    public static function createDataHistory(string $title, string $type, string $file_path = null, array $filters = []): self
    {
        $number = self::max('ref') + 1;
        $ref = str_pad($number, 6, '0', STR_PAD_LEFT);
        return self::create([
            'ref' => $ref,
            'title' => $title,
            'type' => $type,
            'status' => DataHistoryEnum::PROCESSING->value,
            'file_path' => $file_path,
            'filters' => $filters ? json_encode($filters) : null,

        ]);
    }



    public static function updateDataHistoryStatus(string $ref, $notification_id = null): bool
    {
        $dataHistory = self::where('ref', $ref)->first();
        if (!$dataHistory) {
            return false;
        }
        $dataHistory->status = DataHistoryEnum::READY->value;
        $dataHistory->notification_id = $notification_id;
        return $dataHistory->save();
    }


    public function scopeFilter($q)
    {
        return $q->when(request()->has('request_no'), function ($query) {
                    $query->where('ref', 'ILIKE', '%' . request()->get('request_no') . '%');
                })->when(request()->has('request_name'), function ($query) {
                    $query->where('title', 'ILIKE', '%' . request()->get('request_name') . '%');
                })->when(request()->has('type'), function ($query) {
                    $query->whereIn('type', Arr::wrap(request()->get('type')));
                })->when(request()->has('status'), function ($query) {
                    $query->whereIn('status', Arr::wrap(request()->get('status')));
                })->when(request()->has('ids'), function ($query) {
                    $query->whereIn('id', Arr::wrap(request()->get('ids')));
                })->when(request()->has('request_at_from') && request()->has('request_at_to'), function ($query) {
                   
                    $query->whereBetween('created_at', [request()->get('request_at_from'),request()->get('request_at_to')]);

                })->when(request()->has('replied_at_from') && request()->has('replied_at_to'), function ($query) {
                    $query->whereHas('notification', function ($query) {
                        $query->whereBetween('updated_at', [request()->get('replied_at_from'),request()->get('replied_at_to')]);
                    });
                })
                ->when(request()->has('search'), function ($query) {
                    $query->where(function ($query) {
                        $query->where('ref', 'ILIKE', '%' . request()->get('search') . '%')
                        ->orWhere('title', 'ILIKE', '%' . request()->get('search') . '%')
                        ;
                    });
                });
    }

    public function isProcessing(): bool
    {
        return $this->status === DataHistoryEnum::PROCESSING->value;
    }

    public function isAccountReportImport(): bool
    {
        return $this->type  === DataHistoryEnum::IMPORT->value
            && $this->title === DataHistoryEnum::ACCOUNT_REPORT->value;
    }

    public function isBacklogImport(): bool
    {
        return $this->type  === DataHistoryEnum::IMPORT->value
            && $this->title === DataHistoryEnum::IMPORT_BACKLOG_CLAIMS->value;
    }

    public function isAssignedClaims(): bool
    {
        return $this->type  === DataHistoryEnum::IMPORT->value
            && $this->title === DataHistoryEnum::ASSIGNED_CLAIMS->value;
    }

    public function markAsCancelled(): void
    {
        $this->status = DataHistoryEnum::CANCELLED->value;
        $this->save();
    }

    public function getCacheKey(): string
    {
        return "update_billed:{$this->ref}:processed_ids";
    }

    public function getImportCacheKey(): string
    {
        return "import_claims:{$this->ref}:claim_ids";
    }
    public function getImportNewClaimCacheKey(): string
    {
        return "import_new_claims:{$this->ref}:ids";
    }
    public function getBulkAssignCacheKey(): string
    {
        return "bulk_assign:{$this->ref}:ids";
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }


    protected function filtersFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                $filtersArray = json_decode($this->filters, true) ?? [];

                return collect($filtersArray)
                    ->filter(fn ($value) => $value !== null)
                    ->map(function ($value, $key) {
                        $label = ucwords(str_replace('_', ' ', $key));
                        $displayValue = is_array($value) ? implode('/', $value) : $value;
                        return "{$label}: {$displayValue}";
                    })
                    ->implode(', ');
            }
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'ref' => $this->ref,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'file_path' => $this->file_path,
            'created_at' => $this->created_at->toDateTimeString(),
            'replied_at' => $this->notification?->updated_at->toDateTimeString(),
            'notification' => $this->notification,
            'filters' => json_decode($this->filters),
        ];
    }
}
