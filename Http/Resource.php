<?php

namespace Sphere\Api\Http;

use Illuminate\Http\Resources\Json\JsonResource;

class Resource extends JsonResource
{
    use WithResponseTrait;

    public $with = [
        'success' => true,
    ];

    public static function collection($resource, $wrapResult = false)
    {
        $calledClass = get_called_class();
        $collectionClass = $calledClass . 'Collection';
        if (!class_exists($collectionClass)) {
            $collectionClass = ResourceCollection::class;
        }
        return new $collectionClass($resource, $calledClass, $wrapResult);
    }

    protected function attrDate($datetime)
    {
        return $datetime ? $datetime->toIso8601String() : $datetime;
    }

    public function toArray($request)
    {
        return is_array($this->resource)
            ? $this->resource
            : $this->resource->toArray();
    }
}
