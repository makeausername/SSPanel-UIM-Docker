<?php

declare(strict_types=1);

namespace App\Exceptions;

use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class IMDeliveryException extends RuntimeException implements GuzzleException
{
}
