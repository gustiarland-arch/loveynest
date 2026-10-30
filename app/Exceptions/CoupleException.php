<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A couple-space rule was not met. The message is safe to show to the user.
 */
class CoupleException extends RuntimeException {}
