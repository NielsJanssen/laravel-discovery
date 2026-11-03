<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use NielsJanssen\Laravel\Discovery\Command\ConsoleArgument;
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\ConsoleOption;

class TypedInputCommand
{
    /** @var array<string, mixed> */
    public static array $captured = [];

    #[ConsoleCommand(name: 'fixture:typed-enums')]
    public function enums(
        #[ConsoleArgument]
        Status $status,
        #[ConsoleArgument]
        ?Priority $priority = null,
        #[ConsoleOption]
        Status $state = Status::Draft,
        #[ConsoleOption]
        ?Priority $level = null,
    ): void {
        self::$captured = compact('status', 'priority', 'state', 'level');
    }

    #[ConsoleCommand(name: 'fixture:typed-required-option')]
    public function requiredOption(
        #[ConsoleOption]
        Status $status,
    ): void {
        self::$captured = compact('status');
    }

    #[ConsoleCommand(name: 'fixture:typed-dates')]
    public function dates(
        #[ConsoleArgument]
        DateTimeInterface $at,
        #[ConsoleOption]
        ?Carbon $carbon = null,
        #[ConsoleOption]
        ?CarbonImmutable $immutable = null,
        #[ConsoleOption]
        ?CarbonInterface $interface = null,
        #[ConsoleOption]
        ?DateTime $mutable = null,
        #[ConsoleOption]
        ?DateTimeImmutable $native = null,
        #[ConsoleOption(format: 'd-m-Y')]
        ?CarbonImmutable $day = null,
        #[ConsoleOption]
        CarbonImmutable $since = new CarbonImmutable('2020-01-01'),
    ): void {
        self::$captured = compact('at', 'carbon', 'immutable', 'interface', 'mutable', 'native', 'day', 'since');
    }

    #[ConsoleCommand(name: 'fixture:typed-formatted')]
    public function formatted(
        #[ConsoleArgument(format: 'Y-m-d')]
        DateTimeImmutable $on,
    ): void {
        self::$captured = compact('on');
    }

    #[ConsoleCommand(name: 'fixture:typed-unattributed')]
    public function unattributed(
        CarbonImmutable $now,
        Suit $suit = Suit::Spades,
        Status $status = Status::Published,
    ): void {
        self::$captured = compact('now', 'suit', 'status');
    }

    #[ConsoleCommand(name: 'fixture:typed-nullable-date')]
    public function nullableDate(
        #[ConsoleArgument]
        ?CarbonImmutable $from,
    ): void {
        self::$captured = compact('from');
    }

    #[ConsoleCommand(name: 'fixture:typed-injected')]
    public function injected(#[ConsoleArgument] Status $status, Collection $collection): void
    {
        self::$captured = compact('status', 'collection');
    }
}
