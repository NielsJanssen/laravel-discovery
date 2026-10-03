<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use GraphQL\Deferred;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\KeyLoader;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Load;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Loaders;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Review
{
    /** @var list<Loaders> */
    public static array $registries = [];

    #[Load(KeyLoader::class, model: Writer::class, key: 'writerId')]
    public ?Writer $writer = null;

    #[Load(KeyLoader::class, model: Novel::class, key: 'writerId', column: 'writer_id', many: true), Field(of: Novel::class)]
    public array $novelsByWriter = [];

    public function __construct(
        #[Field(type: 'ID')]
        public int $id,
        public int $writerId,
    ) {}

    #[Field(type: Writer::class, nullable: true)]
    public function deferredWriter(Loaders $loaders): Deferred
    {
        self::$registries[] = $loaders;

        return $loaders->defer(KeyLoader::class, $this, ['model' => Writer::class, 'key' => 'writerId']);
    }
}
