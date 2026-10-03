<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class BookMutations
{
    public static ?CreateBook $received = null;

    #[Mutation]
    public function createBook(CreateBook $input): string
    {
        self::$received = $input;

        return $input->title;
    }

    #[Mutation]
    public function draftBook(#[Arg('data', description: 'Draft contents')] ?CreateBook $draft = null): string
    {
        self::$received = $draft;

        return $draft->title ?? 'empty';
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
