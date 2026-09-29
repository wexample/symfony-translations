<?php

namespace Wexample\SymfonyTranslations\Attribute;

use Attribute;

/**
 * Marks an entity field as content to serve in the reader's language.
 *
 * The field keeps its source text, written in the default locale; the other
 * languages live in the content_translation table, made the first time they
 * are asked for and made again when the source changes. Nothing else is
 * required from the entity: no interface, no column, no relation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Translatable
{
}
