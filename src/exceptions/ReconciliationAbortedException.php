<?php

namespace Noo\CraftCloudinary\exceptions;

class ReconciliationAbortedException extends \RuntimeException
{
    public const REASON_EMPTY_RESPONSE = 'empty_response';

    public const REASON_DELETION_RATIO = 'deletion_ratio';

    public function __construct(
        string $message,
        public readonly int $volumeId,
        public readonly string $reason,
        public readonly int $wouldDelete,
        public readonly int $craftCount,
        public readonly array $wouldDeleteIds = [],
    ) {
        parent::__construct($message);
    }
}
