<?php

namespace MdTech\Elasticsearch\Facades;

class Elastic extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return 'elastic';
    }
}