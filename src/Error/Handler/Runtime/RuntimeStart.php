<?php

namespace Kraken\Error\Handler\Runtime;

use Exception;
use Kraken\Error\ErrorHandlerBase;
use Kraken\Error\ErrorHandlerInterface;

class RuntimeStart extends ErrorHandlerBase implements ErrorHandlerInterface
{
    /**
     * @var string[]
     */
    protected $requires = [
        'origin'
    ];

    /**
     * @param Exception $ex
     * @param mixed[] $params
     * @return mixed
     */
    protected function handler(Exception $ex, $params = [])
    {
        return $this->runtime->manager()->startRuntime($params['origin']);
    }
}