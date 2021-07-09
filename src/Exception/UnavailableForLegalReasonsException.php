<?php

declare(strict_types=1);

namespace Pollen\Routing\Exception;

use Exception;

class UnavailableForLegalReasonsException extends HttpException
{
    public function __construct(
        string $message = 'Unavailable For Legal Reasons',
        ?string $title = null,
        ?Exception $previous = null,
        int $code = 0
    ) {
        parent::__construct(451, $message, $title, $previous, [], $code);
    }
}
