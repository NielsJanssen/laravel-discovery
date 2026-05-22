<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

class UnattributedListener
{
    public function handle(EventA $event): void {}
}
