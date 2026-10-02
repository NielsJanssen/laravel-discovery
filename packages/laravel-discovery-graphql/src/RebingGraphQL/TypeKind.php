<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

enum TypeKind: string
{
    case Enum = 'enum';
    case Input = 'input';
    case Interface = 'interface';
    case Object = 'object';
    case Union = 'union';

    public function usableAt(Position $position): bool
    {
        return match ($this) {
            self::Enum => true,
            self::Input => $position === Position::Input,
            self::Interface, self::Object, self::Union => $position === Position::Output,
        };
    }
}
