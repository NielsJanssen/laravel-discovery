<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class BookPatches
{
    public static ?object $received = null;

    #[Mutation]
    public function updateBook(UpdateBook $input): string
    {
        self::$received = $input;

        return 'ok';
    }

    #[Mutation]
    public function patchBook(#[AsArgs] UpdateBook $patch): string
    {
        self::$received = $patch;

        return 'ok';
    }

    #[Mutation]
    public function retagBook(RetagBook $input): string
    {
        self::$received = $input;

        return 'ok';
    }

    #[Mutation]
    public function assignBook(AssignBook $input): string
    {
        self::$received = $input;

        return 'ok';
    }

    #[Mutation]
    public function assignFlat(#[AsArgs] AssignBook $assign): string
    {
        self::$received = $assign;

        return 'ok';
    }

    #[Mutation]
    public function assignBatch(AssignBatch $input): string
    {
        self::$received = $input;

        return 'ok';
    }

    #[Mutation]
    public function renameBook(RenameBook $input): string
    {
        self::$received = $input;

        return 'ok';
    }

    #[Mutation]
    public function renameFlat(#[AsArgs] RenameBook $rename): string
    {
        self::$received = $rename;

        return 'ok';
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
