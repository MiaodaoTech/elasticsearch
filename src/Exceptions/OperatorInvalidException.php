<?php

namespace MdTech\Elasticsearch\Exceptions;

class OperatorInvalidException extends \Exception
{
    protected $code = 500;

    protected $message = 'Invalid Operator in Where Search';
}