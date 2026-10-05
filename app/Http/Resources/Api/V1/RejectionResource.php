<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RejectionResource extends JsonResource
{

     protected $show;

    public function __construct($resource, $show = 0)
    {
        parent::__construct($resource);
        $this->show = $show;
    }
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       // Use the attachments relation (File models via pivot) as the primary
       // source — each File has a UUID and knows its storage driver. Fall back
       // to the legacy JSON paths column only for rows without pivot records.
       $attachments = $this->relationLoaded('attachments')
           ? $this->attachments
           : $this->attachments()->get();

       if ($attachments->isNotEmpty()) {
           $files = $attachments->map(fn ($file) => [
               'file' => FileResource::from($file),
           ]);
       } else {
           $files = collect(json_decode($this->files ?? '[]'))
               ->map(fn ($path) => [
                   'file' => FileResource::from(null, $path),
               ]);
       }

        $response =  [
            'id' => $this->id,
            'reason' => $this->reason,
            'user_id' => $this->user_id,
            'approved_by' => $this->approvedBy?->name,
            // 'supervisor' => $this->supervisorUser?->name,
            // 'type' => $this->type,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'files' =>$files??[],

        ];

        if ($this->show == 1) {
            // Include extra fields when $show = 1
            $response['claim'] = [
                'id' => $this->claim_id,
                'claim_number' => $this->claim?->claim_number,
                "debtor_name" => $this->claim?->debtor_name,
                "debtor_nid" => $this->claim?->debtor_nid,
                "debtor_phone" => $this->claim?->debtor_phone,
                'remark' => $this->claim?->remark,
            ];
        }else{
            
            $response['claim_id'] = $this->claim_id;
            $response['claim_number'] = $this->claim?->claim_number;
        }

        return $response;
    }
}
