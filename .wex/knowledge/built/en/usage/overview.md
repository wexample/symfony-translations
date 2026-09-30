`symfony_translations` is a Symfony bundle whose `Wexample\SymfonyTranslations\Translation\Translator` wraps the framework translator and builds one domain per translation file: it scans the project's `translations/` directory and every path listed in the `translations_paths` parameter for `*.<locale>.yml` files, and `*.trans.yml` ones holding every locale of an element, derives the domain from each file's path, and resolves the YAML includes and cross-file references they contain before adding the result to the catalogue. Keys may carry their domain (`app.pages.home::title`), and a stack of named domains lets a template address its own file through an alias — `setDomain('page', 'app.pages.home')` makes `@page::title` resolve, and `revertDomain('page')` puts the previous one back. A key missing in a language is read in its fallback.

It is meant for Symfony applications that keep one small translation file per page, component, form or entity — the domain types the translator knows about — instead of a few large catalogues, and for the bundles that ship such files alongside the application's own.

The same bundle serves such an application in several languages:

- every page url under its language, `/{_locale}/…`, the api and system routes keeping theirs;
- `translations:translate-files` writing each language of each interface element — its `.<locale>.yml`, or its block of the `.trans.yml` — only what is missing or changed;
- `#[Translatable]` entity fields read in the reader's language, translated on first read and stored, or ahead of time with `translations:translate-content`.

Both translations go through one engine interface, left to plug: until then texts are copied as they are, and replaced on the first run with a real engine. The cookbook's [translate the application](../cookbook/translate-the-application) walks through all three.
