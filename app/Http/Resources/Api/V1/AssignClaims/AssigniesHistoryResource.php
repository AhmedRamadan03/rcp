<?php

namespace App\Http\Resources\Api\V1\AssignClaims;

use App\Http\Resources\Api\GeneralCollection;

class AssigniesHistoryResource extends GeneralCollection
{
    protected $isReport;

    public function __construct($resource, $isReport = false)
    {
        parent::__construct($resource);
        $this->isReport = $isReport;
    }

    protected function collectionData()
    {
        return $this->collection->map(function ($item) {
            return new AssignedHistoryResource($item, $this->isReport);
        });
    }
}