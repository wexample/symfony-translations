# Symfony Translations Extension

An extension for Symfony's Translation component that adds support for YAML includes, references, and inheritance through integration with the [wexample/php-yaml](https://github.com/wexample/php-yaml) package.

Developed by [Wexample](https://wexample.com).

## Features

- **YAML Includes**: Reference translations from other YAML files using the `@domain::key` syntax
- **Domain Aliases**: Create aliases for translation domains to simplify references
- **Context-Aware Translations**: Automatically resolve translation paths based on the current controller/route
- **Inheritance**: Extend translation files with the `~extends` directive
- **Debug Tools**: Twig functions to debug translation domains, locales, and catalogs

## Installation

```bash
composer require wexample/symfony-translations
```

## Basic Usage

### In Twig Templates

```twig
{# Use domain aliases for cleaner translation references #}
{{ '@page::body' | trans }}

{# Debug translation information #}
{{ dump_trans() }}
{{ dump_trans_locales() }}
{{ dump_trans_domains() }}
```

### In YAML Files

```yaml
# Reference another translation
title: "@common.labels::welcome"

# Use the same key from another domain
description: "@common.labels::%"

# Extend another translation file
~extends: "@common.base"
```

## Console Commands

The package provides several useful console commands to help with translation management:

```bash
# Show all translations for 'en' locale
php bin/console translations:catalogue en

# Show translations for 'en' locale in the 'messages' domain
php bin/console translations:catalogue en --domain=messages
```

```bash
php bin/console translations:locales
```

```bash
# Basic usage
php bin/console translations:trans en "app.welcome"

# With domain and parameters
php bin/console translations:trans en "app.greeting" --domain=messages --parameters='{"name":"John"}'
```
## Testing

```bash
phpunit
