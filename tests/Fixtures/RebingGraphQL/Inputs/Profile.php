<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input(name: 'ProfileFields', description: 'A renamed input')]
final class Profile
{
    public int $volume = 5;

    public readonly string $nickname;

    /** @var list<Shade> */
    #[Field(of: Shade::class)]
    public array $shades = [];

    #[Ignore]
    public string $secret = 'kept';

    public string $shouted {
        set(string $value) {
            $this->shouted = strtoupper($value);
        }
    }

    public function __construct(
        #[Field(name: 'fullName', description: 'First and last name')]
        public string $name,
        #[Field(deprecationReason: 'Use shades')]
        public ?Shade $shade = Shade::Dark,
    ) {}
}
