<?php

namespace Sphere\Api\Http;

use Illuminate\Http\Resources\Json\ResourceCollection as JsonResourceCollection;

class ResourceCollection extends JsonResourceCollection
{
    use WithResponseTrait;

    /**
     * Оборачивать результат параметрами success и data
     * @var boolean
     */
    protected $wrapResult = false;

    public function __construct($resource, $collects, $wrapResult = false)
    {
        $this->collects = $collects;
        $this->wrapResult = $wrapResult;

        parent::__construct($resource);
    }

    public function toArray($request)
    {
        if ($this->wrapResult) {
            return [
                'success' => true,
                'data' => $this->collection,
            ];
        }

        return $this->collection;
    }
}
