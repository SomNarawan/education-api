<?php

namespace App\Exceptions;

use RuntimeException;

class GradeImportRowException extends RuntimeException
{
    public function __construct(
        public readonly int $sourceRow,
        string $message,
    ) {
        parent::__construct("แถว {$sourceRow}: {$message}");
    }
}
