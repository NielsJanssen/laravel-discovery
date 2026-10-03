<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Denied;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Member
{
    /** @var list<string> */
    public static array $resolved = [];

    #[Authorize]
    public string $handle = 'ada';

    #[Authorize('viewContactDetails')]
    public string $email = 'ada@example.com';

    #[Authorize(gate: StaffOnlyGate::class)]
    public string $salary = '100';

    #[Authorize(gate: StaffOnlyGate::class, onDenied: Denied::Error)]
    public ?string $notes = 'Prefers mornings';

    #[Authorize(onDenied: Denied::Error, message: 'Sign in to see the phone number')]
    public string $phone = '555-0100';

    #[Authorize]
    #[Authorize('viewContactDetails')]
    public string $address = 'Analytical Street 1';

    #[Authorize('viewContactDetails', onDenied: Denied::Error)]
    public string $postcode = '1815 AL';

    #[Authorize('viewContactDetails')]
    #[Authorize(gate: StaffOnlyGate::class, onDenied: Denied::Error)]
    public string $vault = 'Engine plans';

    public function __construct(
        public string $name = 'Ada',
    ) {}

    #[Field]
    #[Authorize('viewContactDetails')]
    public function birthday(): string
    {
        self::$resolved[] = 'birthday';

        return '1815-12-10';
    }

    #[Field]
    #[Authorize(onDenied: Denied::Error)]
    public function diary(): string
    {
        self::$resolved[] = 'diary';

        return 'Dear diary';
    }
}
