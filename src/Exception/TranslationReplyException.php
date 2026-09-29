<?php

namespace Wexample\SymfonyTranslations\Exception;

use RuntimeException;

/**
 * The Syrtis translation scenario answered something else than the batch it was
 * sent: no reply, no JSON object, or keys missing.
 */
class TranslationReplyException extends RuntimeException
{
}
