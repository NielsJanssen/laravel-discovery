<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use Livewire\Component;
use NielsJanssen\Laravel\Discovery\Router\Get;

#[Get('/profile', middleware: ['web'])]
class ProfilePage extends Component
{
    public function render(): string
    {
        return <<<'BLADE'
        <div>Discovered Livewire profile page</div>
        BLADE;
    }
}
