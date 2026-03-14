<?php

declare (strict_types = 1);

namespace viavario\ecadclient\Cache;

use Psr\SimpleCache\InvalidArgumentException;

final class FileCacheInvalidArgumentException extends \InvalidArgumentException implements InvalidArgumentException
{
}
