<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;

/** A non-scalar type reference found on an action, a field or an argument, with where it was found. */
final readonly class TypeReference
{
    public function __construct(
        public TypeRef $ref,
        public Position $position,
        /** Describes the referrer in an error message, such as `Method App\Books::find`. */
        public string $referrer,
        /** The attribute an error message tells the user to put `type:` on. */
        public string $attribute,
    ) {}

}
