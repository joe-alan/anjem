<?php

namespace App\Exceptions;

class InsufficientCreditsException extends \RuntimeException
{
    public function __construct(int $currentBalance)
    {
        parent::__construct("Insufficient credits. Current balance: {$currentBalance}", 402);
    }
}
