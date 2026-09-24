# Translations: catalogue cache, entity/DB translations, helpers from network

Opened: 2026-09-24
Updated: 2026-09-24
Author: agent:archeology

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It was written by the 2026-09 network archaeology pass. Read it, then discuss it with the owner: every design choice and recommendation below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2` (entry point, order between packages), then `sources.md.j2` (where the legacy code lives: archive repo, branch checkouts, GitLab issues) and the domain page linked below.
> - Pending owner decisions affecting this work are listed in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/recap.md.j2`, section "Décisions qui t'attendent". Where this todo assumes an answer, treat it as an open question.
> - Safety: `NETWORK/local/network` runs on **production data** (real bookkeeping, real invoices in `var/`, a prod dump in `.wex/mysql/dumps/`) — read its code only, never run anything against it. Anonymize any fixture taken from network (bank exports, FEC, mails contain real names/accounts). Never copy secrets found in its history (Stripe keys, tokens, passwords, private keys).

## Goal

The network Translator features (domains per file, aliases, includes, domain stack) are here. What is missing is listed in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/already-extracted-check.md.j2`, section "Translations". The most important item is performance: the new Translator re-scans and re-resolves every YAML catalogue in its constructor on every request.

Issues: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/152.md` (a lone "@" or "%" is taken for an include; entity keys should use the entity path), `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/173.md`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/043.md`.

## Steps

1. **Cache the resolved catalogue.** Source: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Wex/BaseBundle/Translation/Translator.php` lines 85–133 (`CacheInterface` item `translations_resolved`). Invalidate it in debug mode when a file's mtime changes. Test: a second instantiation does not parse YAML (spy the loader).
2. **`transArray(array $ids, …, string $separator)`.** Source: same file, line 348. Test.
3. **Choice lists from an entity's translation domain.** Source: same file, lines 519–553 (`createChoiceList($entityClass, $values, $group)`, `transEntity`, `buildEntityTransTransKey`), also `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Wex/BaseBundle/Service/AbstractEntityService.php` (`createChoiceList/trans/getTransKey`). Used for status and type selects. Test.
4. **DB translations.** Sources: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Entity/Translation.php`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Repository/TranslationRepository.php` (`translateField(entity, field)`, key `@<tableized>.<id>::<field>`, per request locale, fallback to the key), Twig `trans_entity`/`trans_entity_or_null` in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Wex/BaseBundle/Twig/TranslationExtension.php` line 127. The package already has `AbstractTranslation` + `findByLocaleAndKey`; add `translateField`, `buildKey` and the Twig functions. Needed by the proposed symfony-form-builder. Kernel test.
5. **#152.** Test that `YamlIncludeResolver::isIncludeReference` ignores values such as "@" or "50 %".
6. **Check the generic French labels.** Compare `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Wex/BaseBundle/Resources/translations/messages.fr.yml` (177 lines: labels, months, days, approximate dates, page messages) with the loader and design-system catalogues. Hand over whatever is missing (the design system and the loader ship no `fr` catalogue). `priced.fr.yml` goes to symfony-money.

## Do not

- Do not port `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/src/Service/TranslationParameter/**`: it belongs to the mail/notification domain, rebuilt as a tagged locator there.
