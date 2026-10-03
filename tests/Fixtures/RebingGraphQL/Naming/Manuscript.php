<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use Illuminate\Database\Eloquent\Model;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
class Manuscript extends Model
{
    public $timestamps = false;

    protected $table = 'loader_novels';

    protected $guarded = [];

    public string $workingTitle { get => $this->getAttribute('title'); }
}
