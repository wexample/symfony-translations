## Translatable entities

Content is written in the default locale and read in every other one. The entity keeps its source text; the other languages live in the `content_translation` table, one row per entity, field and language.

### Mark the fields

```php
use Wexample\SymfonyTranslations\Attribute\Translatable;

#[ORM\Entity]
class Article extends AbstractEntity
{
    #[ORM\Column(type: Types::STRING, length: 128)]
    protected string $slug;

    #[Translatable]
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $title;

    #[Translatable]
    #[ORM\Column(type: Types::TEXT)]
    protected string $summary;
}
```

Nothing else is asked of the entity: no interface, no column, no relation. An identifier such as a slug stays out of it. The table itself comes with the bundle's own mapping — generate and run a migration once:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Read them

In a template, `translated()` wraps the entity, or a list of them:

```twig
{% set article = translated(article) %}
<h1>{{ article.title }}</h1>
<p>{{ article.summary }}</p>
<a href="{{ path('article_show', { slug: article.slug }) }}">…</a>
```

The marked fields come in the current language, anything else is read from the entity. The wrapper is read-only: the entity it holds is never changed, so saving it never writes a translation over the source. `translated(article, 'de')` reads another language than the current one.

An entity serialized for the api carries its marked fields in the language of the request: api urls have none, so it comes from the `_locale` cookie or the `Accept-Language` header.

In PHP, the same through `ContentTranslationService`:

```php
$title = $contentTranslationService->translateField($article, 'title');
$fields = $contentTranslationService->translateEntity($article, 'fr'); // ['title' => …, 'summary' => …]
```

### When translations are made

A field is translated the first time it is read in a language — synchronously, every missing field of the entity in one call to the engine — then stored. It is made again when its source changes: each row keeps the hash of the source it was made from.

To translate ahead of the readers:

```bash
php bin/console translations:translate-content fr es de                  # every class with #[Translatable] fields, into each locale
php bin/console translations:translate-content fr --entity='App\Entity\Article'   # one class
php bin/console translations:translate-content fr --force                # redo what an engine made
```

### Wording written by hand

```php
$contentTranslationService->setManualTranslation($article, 'title', 'fr', 'Bienvenue');
```

A row written by hand has no engine: neither a change of source nor `--force` replaces it.
