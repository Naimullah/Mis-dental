<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

class ActiveUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->where('is_active', true);
    }
}
