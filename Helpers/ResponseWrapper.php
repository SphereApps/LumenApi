<?php

namespace Sphere\Api\Helpers;

use Illuminate\Http\Response;
use Sphere\Api\Http\Resource;

class ResponseWrapper
{

    protected $resourceClass = Resource::class;

    public function __construct(string $resourceClass = null)
    {
        if ($resourceClass) {
            $this->resourceClass = $resourceClass;
        }
    }

    public function collection($content)
    {
        $resource = $this->resourceClass;
        return $resource::collection($content, true);
    }

    public function item($content)
    {
        $resource = $this->resourceClass;

        return new $resource($content);
    }

    public function success($content)
    {
        if (is_string($content)) {
            $content = [
                'message' => $content,
            ];
        }
        return array_merge(['success' => true], $content);
    }

    public function error($error, array $extra = [])
    {
        $code = null;
        $message = null;

        if (is_numeric($error)) {
            $code = $error;
            $error = [
                'message' => trans("error.{$code}"),
                'code' => $code,
            ];
        } elseif (is_string($error)) {
            $error = [
                'message' => $error,
            ];
        }

        if ($extra) {
            $error = array_merge($error, $extra);
        }

        $error['success'] = false;

        return $error;
    }
}
