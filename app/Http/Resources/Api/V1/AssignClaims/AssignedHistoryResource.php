<?php

namespace App\Http\Resources\Api\V1\AssignClaims;

use App\Http\Resources\Api\GeneralCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class AssignedHistoryResource extends JsonResource
{

    protected $isReport;

    public function __construct($resource, $isReport = false)
    {
        parent::__construct($resource);
        $this->isReport = $isReport;
    }

    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'supervisor' => $this->supervisorUser?->name ?? "",
            'from_user' =>$this->fromUser?->name ?? "",
            'to_user' =>$this->toUser?->name ?? "",
            'assign_date' => $this->assign_date,
            'status' => $this->is_active ? 'Active' : 'Inactive',
        ];

        if ($this->isReport) {
            $response['claim_id'] = $this->claim_id ?? "";
            $response['claim_number'] = $this->claim?->claim_number ?? "";
        }

        return $response;
    }

    
}