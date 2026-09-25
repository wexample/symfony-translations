# symfony_translations

Version: 4.0.7

`symfony_translations` is a Symfony bundle whose `Wexample\SymfonyTranslations\Translation\Translator` wraps the framework translator and builds one domain per translation file: it scans the project's `translations/` directory and every path listed in the `translations_paths` parameter for `*.<locale>.yml` files, derives the domain from each file's path, and resolves the YAML includes and cross-file references they contain before adding the result to the catalogue. Keys may carry their domain (`app.pages.home::title`), and a stack of named domains lets a template address its own file through an alias — `setDomain('page', 'app.pages.home')` makes `@page::title` resolve, and `revertDomain('page')` puts the previous one back.

It is meant for Symfony applications that keep one small translation file per page, component, form or entity — the domain types the translator knows about — instead of a few large catalogues, and for the bundles that ship such files alongside the application's own.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The bundle is one service with satellites. `Wexample\SymfonyTranslations\Translation\Translator` decorates Symfony's `translator`, builds every catalogue itself from YAML files found on disk, and delegates the actual message formatting back to the framework translator it wraps. Everything else in src — two Twig extensions, three console commands, a pair of Doctrine base classes — either reads that service or lives beside it.

### Wiring

src/Resources/config/services.yaml declares the decoration:

```yaml
    Wexample\SymfonyTranslations\Translation\Translator:
      decorates: translator
      arguments:
        - '@translator.default'
      public: false
```

The first argument is the inner `Symfony\Bundle\FrameworkBundle\Translation\Translator`, kept as a public readonly property `$translator`; `KernelInterface` and `ParameterBagInterface` are autowired. The same file autoconfigures `{Command,Service,Twig}` with `controller.service_arguments`.

src/DependencyInjection/WexampleSymfonyTranslationsExtension.php loads that file and merges configuration into the `translations_paths` container parameter rather than replacing it:

```php
        $existing = $container->hasParameter('translations_paths')
            ? (array) $container->getParameter('translations_paths')
            : [];
        $container->setParameter('translations_paths', array_merge($existing, $paths['translations_paths']));
```

The single configuration node is `wexample_symfony_translations.translations_paths`, a list of scalars (src/DependencyInjection/Configuration.php). Inherited from `AbstractWexampleSymfonyExtension`, `prepend()` also registers src/Entity as a Doctrine attribute mapping.

### Boot: the constructor does all the loading

`Translator::__construct` runs four phases, in order:

1. Collect locales — the inner translator's current locale, then each of `getFallbackLocales()`, into `$locales`.
2. `loadTranslationPaths($kernel->getProjectDir())` — the `translations_paths` parameter plus `$pathProject . '/translations/'`, passed through `FileHelper::filterNonExisting()`. `array_filter` preserves keys, which matters: a **string** key survives and becomes the domain prefix for every file under that path (a leading `@` is stripped), so a bundle registering `'@AcmeBundle' => '/path/to/translations'` gets domains named `AcmeBundle.…`.
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

`trans()` is the only read path and it is short:

- `ensureCataloguePopulated($locale)` — a locale first seen at runtime gets its files scanned and its catalogue populated then, guarded by the `$cataloguesPopulated` flags. `setLocale()` clears the flag for the locale it switches to.
- If no `$domain` argument was given, the domain is split off the id at `::` (`app.pages.home::title`), and `resolveDomain()` maps a leading `@` to the domain stack.
- The message is delegated to the inner translator only `if ($forceTranslate || ($domain && $catalogue->has($id, $domain)))`. Otherwise the untranslated `$default` — the original id, or `domain::id` when a domain was extracted — is returned as-is, so a missing key surfaces as itself rather than as an exception.

Note that the existence check reads `$this->translator->getCatalogue()`, the current locale's catalogue, even when a `$locale` argument is passed.

### The domain stack

`$domainsStack` maps a name to a stack of domains. `setDomain('page', 'app.pages.home')` pushes, `revertDomain('page')` pops, `getDomain()` returns the top, and `resolveDomain('@page')` turns the alias into whatever is currently on top — that is what lets a shared template write `@page::title` and mean a different file on each render. `setDomainFromTemplatePath()` is the convenience that derives the domain from a template path and pushes it in one call.

`buildEntityAliases()` populates the same stack from the catalogue at boot: any domain matching `/(?:^|\.)entity\.(.+)$/` — `front.entity.metric`, `WexampleSymfonyMoneyBundle.entity.currency` — gets an `entity.<name>` alias. First registration wins, and app paths are loaded before bundle ones, so an application file shadows a bundle's. `getEntityAliases()` exposes the resulting map, for client-side resolution.

`transFilter('welcome.*')` is the other consumer of the stack: it resolves the domain, turns `*` into `.*` in a regex, and returns the matching catalogue entries keyed by `domain::id`.

### Twig and console surfaces

src/Twig/TranslationExtension.php exposes `translation_build_domain_from_template_path()` and `translation_debug_info()`. src/Twig/TranslationDebugExtension.php adds three `dump()`-based helpers — `dump_trans`, `dump_trans_locales`, `dump_trans_domains` — the last one dumping the domain stack.

The three commands in src/Command all extend `AbstractTranslationCommand`, which injects the `Translator` and ties the command to the bundle through `getBundleClassName()`: `LocalesCommand` lists `getAllLocales()`, `CatalogueCommand` tables `getCatalogue($locale)->all()` with an optional `--domain` filter, and `TransCommand` translates one key with JSON-encoded `--parameters`.

### Entity side

src/Entity/AbstractLocale.php, src/Entity/AbstractTranslation.php and the two abstract repositories are mapped superclasses for an application that stores translations in database rows — `key`, `value`, `updatedAt`, a locale relation the concrete class must declare. Nothing in `Translator` reads them; they are a separate facility shipped by the same bundle, and the `entity.*` domain aliases above are unrelated to them.

### Tests

src/Tests/AbstractTranslationTest.php ships inside `src/` — it is part of the public surface — and builds a `Translator` over a stubbed `SymfonyTranslator`, a stub kernel returning `__DIR__` as project dir, and a parameter bag returning one test path. The unit tests under tests/Unit exercise domain building, the stack, and flattening against it.

tests/Integration/TranslationColdCacheTest.php is the one that runs the real thing: it deletes the kernel cache directory, boots tests/Fixtures/App/AppKernel.php — a fixture app registering only this bundle — renders `page.html.twig` and asserts it prints `Bonjour`. That covers the case the decoration is most fragile in, a container built from scratch. It runs in a separate process, with global handlers snapshotted.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- symfony/translation: >=6.2
- wexample/symfony-helpers: >=11.0.0
- wexample/symfony-testing: >=3.0.0
- wexample/php-helpers: >=4.0.0
- wexample/php-yaml: >=1.0.70
- wexample/symfony-template: >=2.0.0

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
