<?php

namespace MatteoMeloni\DynamoDb\Facades;

use Illuminate\Support\Facades\Facade;

class DynamoDb extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'dynamodb';
    }
}
