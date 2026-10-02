# symfony_translations

Version: 8.0.0

`symfony_translations` is a Symfony bundle whose `Wexample\SymfonyTranslations\Translation\Translator` wraps the framework translator and builds one domain per translation file: it scans the project's `translations/` directory and every path listed in the `translations_paths` parameter for `*.<locale>.yml` files, and `*.trans.yml` ones holding every locale of an element, derives the domain from each file's path, and resolves the YAML includes and cross-file references they contain before adding the result to the catalogue. Keys may carry their domain (`app.pages.home::title`), and a stack of named domains lets a template address its own file through an alias — `setDomain('page', 'app.pages.home')` makes `@page::title` resolve, and `revertDomain('page')` puts the previous one back. A key missing in a language is read in its fallback.

It is meant for Symfony applications that keep one small translation file per page, component, form or entity — the domain types the translator knows about — instead of a few large catalogues, and for the bundles that ship such files alongside the application's own.

The same bundle serves such an application in several languages:

- every page url under its language, `/{_locale}/…`, the api and system routes keeping theirs;
- `translations:translate-files` writing each language of each interface element — its `.<locale>.yml`, or its block of the `.trans.yml` — only what is missing or changed;
- `#[Translatable]` entity fields read in the reader's language, translated on first read and stored, or ahead of time with `translations:translate-content`.

Both translations go through one engine interface, left to plug: until then texts are copied as they are, and replaced on the first run with a real engine. The cookbook's [translate the application](../cookbook/translate-the-application) walks through all three.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The bundle is one service with satellites. `Wexample\SymfonyTranslations\Translation\Translator` decorates Symfony's `translator`, builds every catalogue itself from YAML files found on disk, and delegates the actual message formatting back to the framework translator it wraps. Around it sit three facilities that read it or live beside it: localized urls, the translation of interface files, and the translation of entity content — the last two going through one engine interface.

### Wiring

src/Resources/config/services.yaml declares the decoration:

```yaml
    Wexample\SymfonyTranslations\Translation\Translator:
      decorates: translator
      arguments:
        - '@translator.default'
      public: false
```

The first argument is the inner `Symfony\Bundle\FrameworkBundle\Translation\Translator`, kept as a public readonly property `$translator`; `KernelInterface` and `ParameterBagInterface` are autowired. The same file autoloads `{Command,EventSubscriber,Repository,Serializer,Service,Twig}`, decorates `routing.loader` with `LocalizedRouteLoader`, aliases `TextTranslatorInterface` to `PendingTextTranslator`, and tags `ContentTranslationNormalizer` as a serializer normalizer at priority 64.

src/DependencyInjection/Configuration.php has two nodes: `translations_paths`, a list merged into the container parameter of the same name rather than replacing it, and `locale_routing` — off unless enabled, with its `excluded_paths`. src/DependencyInjection/WexampleSymfonyTranslationsExtension.php turns the latter into the `wexample_symfony_translations.locale_routing.*` parameters. Inherited from `AbstractWexampleSymfonyExtension`, `prepend()` registers src/Entity as a Doctrine attribute mapping, which is how `content_translation` reaches the application's schema.

### Boot: the constructor does all the loading

`Translator::__construct` runs four phases, in order:

1. Collect locales — the inner translator's current locale, then each of `getFallbackLocales()`, into `$locales`.
2. `loadTranslationPaths($kernel->getProjectDir())` — the `translations_paths` parameter plus `$pathProject . '/translations/'`, passed through `FileHelper::filterNonExisting()`. `array_filter` preserves keys, which matters: a **string** key survives and becomes the domain prefix for every file under that path (a leading `@` is stripped), so a bundle registering `'@AcmeBundle' => '/path/to/translations'` gets domains named `AcmeBundle.…`. `getTranslationPaths()` exposes the result.
3. `loadTranslationFiles()` — for each locale, one `Wexample\PhpYaml\YamlIncludeResolver` is created in `$yamlResolvers[$locale]`, and every path is scanned recursively for files ending in `.<locale>.yml`. Each match is registered under the domain derived from its path.
4. `populateCatalogues()` then `buildEntityAliases()`.

### From file path to domain

`buildDomainFromPath($filePath, $basePath, $bundleName)` computes the file's directory relative to **`dirname($basePath)`** — the parent of the scanned directory, so the scanned directory's own name is part of the domain. The path segments are joined with `.`, the filename minus its locale suffix is appended, and the bundle prefix, if any, is prepended. In the test fixture, `translations/test/messages.fr.yml` under base `<project>/translations/` yields the domain `translations.test.messages`, which is how tests/Fixtures/App/templates/page.html.twig addresses it: `{{ 'translations.test.messages::hello'|trans }}`.

`buildDomainFromTemplatePath()` is the static counterpart for template names: `pages/home/index.html.twig` becomes `pages.home.index`.

### Populating the catalogue

`populateCatalogueForLocale()` asks the resolver for every registered domain, resolves it, flattens it and pushes it in:

```php
                    $resolvedValues = $resolver->resolveValues($values, $domain);
                    $flattenedValues = ArrayHelper::flattenArray($resolvedValues);

                    foreach ($flattenedValues as $key => $value) {
                        if (is_string($value)) {
                            $catalogue->add([$key => $value], $domain);
                        }
                    }
```

Two transformations happen here and nowhere else. `resolveValues()` is where the include syntax of `wexample/php-yaml` is honoured — `@other.domain::key` references, `%` meaning "the same key in the referenced domain", and a `~extends` entry pulling a parent file's keys in. `flattenArray()` turns nested YAML into the dotted keys Symfony catalogues expect (`group.nested_key`). Non-string leftovers are dropped.

### Resolving a key at call time

`trans()` is the only read path:

- `ensureCataloguePopulated($locale)` — a locale first seen at runtime gets its files scanned and its catalogue populated then, guarded by the `$cataloguesPopulated` flags. `setLocale()` clears the flag for the locale it switches to.
- If no `$domain` argument was given, the domain is split off the id at `::` (`app.pages.home::title`), and `resolveDomain()` maps a leading `@` to the domain stack.
- `findLocaleDefining()` walks the locale then its fallbacks, and the message is delegated to the inner translator in the first whose own catalogue `defines()` the key. Otherwise the untranslated `$default` — the original id, or `domain::id` when a domain was extracted — is returned as-is, so a missing key surfaces as itself rather than as an exception.

The fallback is done here because Symfony's own cannot: its fallback catalogues are copies taken before the YAML messages are added. Without it, a language translated in part shows raw keys. `transFilter()` walks the same chain, fallbacks first, so the keys handed to javascript are complete as well.

### The domain stack

`$domainsStack` maps a name to a stack of domains. `setDomain('page', 'app.pages.home')` pushes, `revertDomain('page')` pops, `getDomain()` returns the top, and `resolveDomain('@page')` turns the alias into whatever is currently on top — that is what lets a shared template write `@page::title` and mean a different file on each render. `setDomainFromTemplatePath()` is the convenience that derives the domain from a template path and pushes it in one call.

`buildEntityAliases()` populates the same stack from the catalogue at boot: any domain matching `/(?:^|\.)entity\.(.+)$/` — `front.entity.metric`, `WexampleSymfonyMoneyBundle.entity.currency` — gets an `entity.<name>` alias. First registration wins, and app paths are loaded before bundle ones, so an application file shadows a bundle's. `getEntityAliases()` exposes the resulting map, for client-side resolution.

`transFilter('welcome.*')` is the other consumer of the stack: it resolves the domain, turns `*` into `.*` in a regex, and returns the matching catalogue entries keyed by `domain::id`.

### Localized urls

src/Service/LocaleService.php is the one place the languages are known: `framework.enabled_locales`, the default locale first. It also says which paths are excluded and guesses the language of a request whose url has none — the `_locale` cookie, then `Accept-Language`.

src/Routing/LocalizedRouteLoader.php decorates the root routing loader, so it sees the whole collection once assembled: an application importing its controllers in one resource has no other way to prefix the pages and not the api. Every route not excluded gets `/{_locale}` in front of its path, the locales as requirement and the default locale as default — the latter lets an url be generated outside any request. It adds `locale_root_redirect` on `/`, answered by src/Controller/LocaleRedirectController.php.

src/EventSubscriber/LocaleSubscriber.php completes it on three events:

- request, at priority 17 — after the router, before Symfony's `LocaleListener` which pushes the locale to the router context and the translator — gives a guessed locale to the requests whose route carries none;
- response writes the `_locale` cookie when a page was read in another language than the cookie says;
- exception turns a 404 on an url without its language into a redirect, when the same path under the guessed language matches a route.

src/Twig/LocaleExtension.php exposes `locales()`, each with the url of the current page in that language.

### One way to the engine

src/Interface/TextTranslatorInterface.php takes a batch of texts and returns it under the same keys, and names its engine. src/Service/PendingTextTranslator.php is the default: it returns the texts untouched, its engine name is `pending`. An engine replaces it by aliasing the interface.

src/Translation/SyrtisTextTranslator.php is the engine the `syrtis` configuration enables, through `syrtis/php-client`. It cuts the texts into batches of `max_batch_length` characters, sends each to the session with `SessionRepository::sendMessage(sync: true)` and reads the first reply (`Message::isReply()`): a JSON object of the translations, checked for every key of the batch, or a src/Exception/TranslationReplyException.php. It sits outside `Service/`, which is registered as a whole: it only becomes a service once configured, with a Syrtis client of its own. This dependency of the bundle on Syrtis is a first step; the engine moves out when another one comes.

Nothing calls the engine directly. src/Service/TextTranslationService.php skips texts with no letter in them, masks parameters and markup with `[#n]` tokens through src/Helper/TranslationPlaceholderHelper.php and restores them afterwards. Its `hashSource()` is what every stored translation is checked against.

Both facilities below record the engine name next to each translation, and treat one made by `pending` as stale as soon as another engine is configured — so plugging a real engine redoes the copies without a `--force`.

### Interface files

src/Service/TranslationFileService.php works per element — the path of its files without their suffix — and writes `<name>.<target>.yml` next to each `<name>.<source>.yml` under the translation paths, or, for an element holding a `<name>.trans.yml`, its `<target>:` block. src/Helper/TransFileHelper.php splits that file into top-level blocks as text and replaces one without touching the others; the `Translator` registers each block as the element's domain in its locale, after the per-locale files, through `YamlIncludeResolver::registerContent()`. By default only the application's: a path counts when its resolved location is inside the project and outside its `vendor/`, which excludes bundles installed there and bundles symlinked from elsewhere alike.

The target mirrors the source's keys and order. Per key, `translateTree()` decides from the target and the lock — `.translations.lock.json` at the root of each translation path, recording per target file and key the source hash and the engine — entries named after the per-locale file even for a `.trans.yml`, so that a conversion leaves them valid:

- no string, a reference, `%`, or anything under `~extends` — copied as is;
- present in the target with no lock entry — written by hand, kept;
- absent, forced, source hash changed, or made by `pending` — translated;
- otherwise kept.

A target is rewritten only when its data changed, so a hand-written file is never reformatted. src/Service/LocaleConfigService.php then adds the target locale to `framework.enabled_locales`, editing `config/packages/translation.yaml` line by line so its comments survive.

### Entity content

src/Attribute/Translatable.php marks a property. src/Entity/ContentTranslation.php is one field of one entity in one language, the entity referred to by class and identifier rather than by relation; `engine` null means written by hand.

src/Service/ContentTranslationService.php reads the source values through the Doctrine metadata, compares them with the stored rows, sends every stale field of an entity to the engine in one batch, stores the results and caches them for the request. It never modifies the entity. src/Repository/ContentTranslationRepository.php reads and writes through the connection and never the unit of work: a translation is made while a page is rendered, and a flush there would also save whatever else the request changed.

Two readers sit on top: src/Twig/ContentTranslationExtension.php wraps entities in src/Class/TranslatedEntity.php, a read-only view answering the marked fields translated and anything else from the entity; src/Serializer/ContentTranslationNormalizer.php lets the other normalizers work, then replaces the marked fields in their output.

### Twig and console surfaces

src/Twig/TranslationExtension.php exposes `translation_build_domain_from_template_path()` and `translation_debug_info()`. src/Twig/TranslationDebugExtension.php adds three `dump()`-based helpers — `dump_trans`, `dump_trans_locales`, `dump_trans_domains` — the last one dumping the domain stack.

The commands in src/Command all extend `AbstractTranslationCommand`, which injects the `Translator` and ties the command to the bundle through `getBundleClassName()`:

- `translations:locales` lists `getAllLocales()`;
- `translations:catalogue` tables `getCatalogue($locale)->all()` with an optional `--domain` filter;
- `translations:trans` translates one key with JSON-encoded `--parameters`;
- `translations:translate-files` runs `TranslationFileService` for each target locale, then enables it;
- `translations:convert-files` runs `TranslationStorageService`, moving elements between per-locale files and `.trans.yml`;
- `translations:translate-content` runs `ContentTranslationService`, for each target locale, over every entity of one class, or of every class having marked fields, clearing the entity manager between batches.

### Tests

src/Tests/AbstractTranslationTest.php ships inside `src/` — it is part of the public surface — and builds a `Translator` over a stubbed `SymfonyTranslator`, a stub kernel returning `__DIR__` as project dir, and a parameter bag returning one test path. The unit tests under tests/Unit exercise domain building, the stack and flattening against it, and, on their own: the file translation against a temporary directory and a fake engine that fails when a placeholder reaches it, the route localization, and the normalizer.

tests/Integration/TranslationColdCacheTest.php is the one that runs the real thing: it deletes the kernel cache directory, boots tests/Fixtures/App/AppKernel.php — a fixture app registering only this bundle — renders `page.html.twig` and asserts it prints `Bonjour`. That covers the case the decoration is most fragile in, a container built from scratch. It runs in a separate process, with global handlers snapshotted.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- symfony/translation: >=6.2
- syrtis/php-client: >=4.0.0
- wexample/symfony-helpers: >=12.0.0
- wexample/symfony-testing: >=5.0.0
- wexample/php-helpers: >=6.0.0
- wexample/php-yaml: >=2.0.0
- wexample/symfony-template: >=2.0.0
- symfony/intl: >=6.2
- symfony/serializer: >=6.2

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
