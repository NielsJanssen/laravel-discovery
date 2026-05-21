<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command\Exception;

use Tempest\Discovery\Exceptions\DiscoveryException;

class InvalidCommandRegistrationException extends \Exception implements DiscoveryException
{
    public static function forCommand(string $command, string $reason): self
    {
        return new self(sprintf(
            'Failed to register command "%s": %s',
            $command,
            $reason,
        ));
    }
}
