<?php

namespace Noo\CraftCloudinary\models;

use craft\base\Model;

class Settings extends Model
{
    public bool $enableThumbnailCache = false;

    public int $thumbnailCacheTtl = 604800;

    public function defineRules(): array
    {
        return [
            [['enableThumbnailCache'], 'boolean'],
            [['thumbnailCacheTtl'], 'integer', 'min' => 0],
        ];
    }
}
