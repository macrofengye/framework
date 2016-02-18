<?php

namespace Kraken\Runtime\Command\Thread;

use Kraken\Command\Command;
use Kraken\Command\CommandInterface;
use Kraken\Exception\Runtime\RejectionException;

class ThreadCreateCommand extends Command implements CommandInterface
{
    /**
     * @param mixed[] $params
     * @return mixed
     * @throws RejectionException
     */
    protected function command($params = [])
    {
        if (!isset($params['alias']) || !isset($params['name']) || !isset($params['flags']))
        {
            throw new RejectionException('Invalid params.');
        }

        return $this->runtime->manager()->createThread($params['alias'], $params['name'], (int)$params['flags']);
    }
}