<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
class Novel extends Model
{
    public $timestamps = false;

    protected $table = 'loader_novels';

    protected $guarded = [];

    public string $title { get => $this->getAttribute('title'); }

    #[Field(name: 'author', type: Writer::class), Relation]
    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }
}
