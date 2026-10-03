<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Denied;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
class Writer extends Model
{
    public $timestamps = false;

    protected $table = 'loader_writers';

    protected $guarded = [];

    #[Field(type: 'ID')]
    public int $id { get => $this->getKey(); }

    public string $name { get => $this->getAttribute('name'); }

    #[Field(of: Novel::class), Relation]
    public function novels(): HasMany
    {
        return $this->hasMany(Novel::class)->orderBy('id');
    }

    #[Field(of: 'String'), Files('covers')]
    public function covers(?string $size = null): array
    {
        return [];
    }

    #[Authorize('seeFiles'), Field(of: 'String'), Files('private')]
    public function privateFiles(): array
    {
        return [];
    }

    #[Authorize('seeFiles', onDenied: Denied::Error), Field(of: 'String'), Files('guardedFirst')]
    public function guardedFirst(): array
    {
        return [];
    }

    #[Field(of: 'String'), Files('guardedLast'), Authorize('seeFiles', onDenied: Denied::Error)]
    public function guardedLast(): array
    {
        return [];
    }
}
