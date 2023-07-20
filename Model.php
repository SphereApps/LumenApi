<?php

namespace Sphere\Api;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Model extends Eloquent
{
    /**
     * Validation rules
     * @param  boolean $isCreate
     * @return array
     */
    public function rules()
    {
        return [];
    }

    public function ruleMessages(): array
    {
        return [];
    }
}
