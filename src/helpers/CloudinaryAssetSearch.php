<?php

namespace Noo\CraftCloudinary\helpers;

class CloudinaryAssetSearch
{
    /**
     * Searches uploaded assets in a volume subpath using one request per page.
     *
     * @return \Generator<int, array>
     */
    public static function resources(object $client, string $volumeSubpath, array $fields): \Generator
    {
        $expression = AssetFolders::searchExpression($volumeSubpath);
        $nextCursor = null;

        do {
            $search = $client->searchApi()
                ->expression($expression)
                ->fields($fields)
                ->maxResults(500);

            if ($nextCursor !== null) {
                $search->nextCursor($nextCursor);
            }

            $result = $search->execute()->getArrayCopy();

            foreach ($result['resources'] ?? [] as $resource) {
                yield $resource;
            }

            $nextCursor = $result['next_cursor'] ?? null;
        } while ($nextCursor !== null);
    }
}
