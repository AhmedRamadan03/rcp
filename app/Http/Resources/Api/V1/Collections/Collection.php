<?php

namespace App\Http\Resources\Api\V1\Collections;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class Collection extends ResourceCollection
{
    protected array $resourceArgs;

    public function __construct($resource, public string $message, public $resourceClass, array $resourceArgs = [])
    {
        parent::__construct($resource);
        $this->resourceArgs = $resourceArgs;
    }

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => $this->message,
            'data' => $this->collection->transform(function ($model) {
                return new $this->resourceClass($model, ...$this->resourceArgs);
            }),
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'last_page' => $this->lastPage(),
                'path' => $this->getOptions()['path'],
                'per_page' => $this->perPage(),
                'to' => $this->lastItem(),
                'total' => $this->total(),
            ],
        ];
    }
}