<?php

namespace App\Support;

use Illuminate\Log\Logger;

/**
 * Logging "tap" that attaches the redactor to every handler of a channel.
 */
class RedactSensitiveLogData
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new LogRedactor);
    }
}
