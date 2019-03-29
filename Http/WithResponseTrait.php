<?php

namespace Sphere\Api\Http;

trait WithResponseTrait
{
    public function withResponse($request, $response)
    {
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }
}
