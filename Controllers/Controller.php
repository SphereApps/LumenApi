<?php

namespace Sphere\Api\Controllers;

use Sphere\Api\Http\Resource;
use Sphere\Api\Helpers\ResponseWrapper;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

/**
 * @property ResponseWrapper response
 */
class Controller extends BaseController
{

    protected $options = [];

    protected $defaultOptions = [
        // 'model' => null,
        'resource' => Resource::class,
    ];

    /**************************************************************************
        System
    **************************************************************************/

    /**
     * @var Request $request
     */
    protected $request;

    /**
     * @var Illuminate\Contracts\Auth\Access\Authorizable
     */
    protected $user;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->user = app('auth')->user();

        $this->options = array_merge($this->defaultOptions, $this->options);

        $this->boot();
    }

    protected function boot()
    {
    }

    public function options($key = null)
    {
        return $key ? $this->options[$key] ?? null : $this->options;
    }

    protected function initResponse()
    {
        return new ResponseWrapper($this->options('resource'));
    }

    public function __get($property)
    {
        $initMethod = 'init'.ucfirst($property);
        if (method_exists($this, $initMethod)) {
            $this->$property = $this->$initMethod();
        }

        return $this->$property;
    }
}
