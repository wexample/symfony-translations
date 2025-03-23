# Symfony Translations Extension

An extension for Symfony's Translation component that adds support for YAML includes, references, and inheritance through integration with the [wexample/php-yaml](https://github.com/wexample/php-yaml) package.

Developed by [Wexample](https://wexample.com).

## Installation

```bash
composer require wexample/symfony-translations
```

## Features

- Seamless integration with Symfony's Translation component
- Support for references between translation files using the `@domain::key` syntax
- Support for same-key references via `@domain::%`
- Complete translation file inheritance with `~extends: @domain`
- Support for nested paths with dot notation (`key.subkey.value`)
- High-performance caching system for optimized translation resolution
- Clear separation of concerns between translation catalog management and reference resolution

## Usage

### Basic Setup

```php
use Wexample\PhpYaml\YamlIncludeResolver;
use Wexample\SymfonyTranslations\Translation\Translator;
use Symfony\Component\Translation\Translator as SymfonyTranslator;

// Create a YAML resolver instance
$yamlResolver = new YamlIncludeResolver();

// Create a Symfony translator
$symfonyTranslator = new SymfonyTranslator('en');

// Create our extended translator that uses the resolver
$translator = new Translator($yamlResolver, $symfonyTranslator);

// Use the translator as you would normally use Symfony's translator
$translated = $translator->trans('message.key');
```

### Translation Files Format

```yaml
# messages.en.yml
welcome: "Welcome to our site!"
greeting: "Hello, %name%!"

# Include a key from another domain
footer.copyright: "@legal::copyright"

# Include the same key from another domain
terms: "@legal::%"
```

```yaml
# legal.en.yml
copyright: "© 2025 Our Company. All rights reserved."
terms: "Terms and Conditions apply."
```

### Twig Integration

The package provides a Twig extension for easy integration with Twig templates:

```php
// In your service configuration
use Wexample\SymfonyTranslations\Twig\TranslationExtension;

// Register the extension
$twig->addExtension(new TranslationExtension($translator));
```

Then in your Twig templates:

```twig
{# Simple translation #}
{{ 'welcome'|trans }}

{# With parameters #}
{{ 'greeting'|trans({'%name%': 'John'}) }}

{# References will be automatically resolved #}
{{ 'footer.copyright'|trans }}
```

## Architecture

The package follows a clean separation of concerns:

1. **YamlIncludeResolver** (from wexample/php-yaml): Handles all aspects of resolving references between YAML files
2. **Translator**: Extends Symfony's Translation component to integrate with the resolver

This architecture allows for:

- Better maintainability through single responsibility principle
- Improved performance with multi-level caching
- Flexibility to use the resolver independently of Symfony

### Key Components

- **Translator**: Main class that integrates with Symfony's Translation component
- **TranslationExtension**: Twig extension for easy template integration
- **TransFilter**: Twig filter for translation with reference resolution

## Performance Optimization

The package leverages the caching system from the YamlIncludeResolver to provide optimal performance:

```php
// The first time translations are resolved, it may be slower
$translator->trans('message.with.references');

// Subsequent calls benefit from caching
$translator->trans('message.with.references'); // Much faster
```

## License

MIT
