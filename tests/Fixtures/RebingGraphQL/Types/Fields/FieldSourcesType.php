<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Fields;

use Deprecated;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\ContainerService;

#[Type]
final class FieldSourcesType
{
    public string $plain = 'plain';

    public static string $shared = 'static';

    public float $ratio = 0.5;

    public bool $flag = true;

    public private(set) string $asymmetric = 'asymmetric';

    public string $shout {
        get => strtoupper($this->plain);
    }

    public string $backed = 'backed' {
        get => $this->backed . '!';
    }

    public string $writeOnly {
        set(string $value) {
            $this->plain = $value;
        }
    }

    #[Field(name: 'renamed', description: 'Renamed from $count')]
    public int $count = 3;

    #[Field(nullable: true)]
    public string $widened = 'widened';

    #[Field(nullable: false)]
    public ?string $stillNullable = 'stillNullable';

    /** @var list<?string> */
    #[Field(of: 'string', nullableItems: true)]
    public array $sparse = ['a', null];

    /** @var Collection<int, int> */
    #[Field(of: 'int')]
    public Collection $numbers;

    #[Field(type: 'Int')]
    public mixed $loose = 7;

    public ?FieldSourcesType $parent = null;

    #[Ignore]
    public string $ignored = 'ignored';

    protected string $hidden = 'hidden';

    private string $secret = 'secret';

    public function __construct()
    {
        $this->numbers = collect([1, 2, 3]);
    }

    public function notAField(): string
    {
        return $this->secret . $this->hidden;
    }

    #[Field(description: 'Repeats the prefix')]
    public function withArgs(#[Arg(description: 'Put in front')] string $prefix, ?int $times = null): string
    {
        return str_repeat($prefix, $times ?? 1) . $this->plain;
    }

    #[Field]
    public function withService(ContainerService $service): string
    {
        return $this->plain . $service->suffix();
    }

    #[Field]
    public function withInjections(ResolveInfo $info, #[Root] self $root): string
    {
        return $info->fieldName . ':' . ($root === $this ? 'same' : 'other');
    }

    #[Field]
    #[Deprecated('Use plain', since: '1.2')]
    public function oldName(): string
    {
        return $this->plain;
    }

    #[Field(deprecationReason: 'Explicit reason')]
    #[Deprecated('Native reason')]
    public function explicitDeprecation(): string
    {
        return $this->plain;
    }

    #[Field]
    public function me(): static
    {
        return $this;
    }

    #[Field(type: 'ID')]
    public function key(): int
    {
        return 42;
    }

    #[Ignore]
    public function ignoredMethod(): string
    {
        return 'ignored';
    }
}
