<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
class Author extends Model
{
    public $timestamps = false;

    protected $table = 'loader_writers';

    protected $guarded = [];

    public string $penName { get => $this->getAttribute('name'); }

    #[Field(of: Manuscript::class), Relation]
    public function writtenNovels(): HasMany
    {
        return $this->hasMany(Manuscript::class, 'writer_id')->orderBy('id');
    }
}
