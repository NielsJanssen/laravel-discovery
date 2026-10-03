<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Validation\Rule\Min;
use Workbench\App\Models\User;

final class VolumeQueries
{
    public static ?ShelveVolume $received = null;

    #[Query]
    public function latestVolume(): Volume
    {
        return new Volume();
    }

    /**
     * @return list<Volume>
     */
    #[Query(of: Volume::class)]
    public function volumesByAuthor(#[Min(3, message: 'Name at least three letters.')] string $authorName, ?int $maxCount = null): array
    {
        return array_fill(0, $maxCount ?? 1, new Volume(title: $authorName));
    }

    #[Query(name: 'volumeCount')]
    public function countVolumes(#[Arg(name: 'onShelf')] ?string $shelfLabel = null): int
    {
        return $shelfLabel === null ? 0 : strlen($shelfLabel);
    }

    #[Query]
    public function ownerName(User $volumeOwner, #[Arg(name: 'coOwner')] ?User $secondOwner = null): string
    {
        return $volumeOwner->name . ($secondOwner === null ? '' : " & {$secondOwner->name}");
    }

    #[Mutation]
    public function shelveVolume(ShelveVolume $shelveRequest): string
    {
        self::$received = $shelveRequest;

        return $shelveRequest->shelfLabel;
    }
}
