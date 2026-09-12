<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\Dimensions;
use NielsJanssen\Laravel\Validation\Rule\Extensions;
use NielsJanssen\Laravel\Validation\Rule\File;
use NielsJanssen\Laravel\Validation\Rule\Image;
use NielsJanssen\Laravel\Validation\Rule\Mimes;
use NielsJanssen\Laravel\Validation\Rule\Mimetypes;

final class FileRules
{
    #[File]
    public mixed $file = null;

    #[Mimes(['jpg', 'png'])]
    public mixed $mimes = null;

    #[Mimetypes(['image/jpeg'])]
    public mixed $mimetypes = null;

    #[Extensions(['jpg'])]
    public mixed $extensions = null;

    #[Image]
    public mixed $image = null;

    #[Image(allowSvg: true)]
    public mixed $svg = null;

    #[Dimensions(minWidth: 100, maxHeight: 200, ratio: '3/2')]
    public mixed $dimensions = null;

    #[Dimensions(ratioBetween: ['1/2', '3/2'])]
    public mixed $ratioBetween = null;
}
