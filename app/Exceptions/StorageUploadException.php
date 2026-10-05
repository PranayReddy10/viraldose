<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a file could not be written to the configured media storage
 * (local disk or DigitalOcean Spaces). Rendered as a form error so editors
 * see the real reason instead of a silently missing image.
 */
class StorageUploadException extends RuntimeException {}
