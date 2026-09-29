<?php

namespace Wexample\SymfonyTranslations\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Languages;

/**
 * The locales the application speaks, and how each one names itself.
 *
 * The list is framework.enabled_locales, the one Symfony already reads, so
 * there is no second list to keep in step. An application that enables none
 * speaks its default locale only.
 */
class LocaleService
{
    final public const string LOCALE_ATTRIBUTE = '_locale';

    final public const string LOCALE_COOKIE = '_locale';

    /**
     * @param string[] $enabledLocales
     * @param string[] $excludedPaths
     */
    public function __construct(
        private readonly string $defaultLocale,
        private readonly array $enabledLocales,
        private readonly bool $routingEnabled,
        private readonly array $excludedPaths,
    ) {
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * @return string[] Default locale first
     */
    public function getLocales(): array
    {
        return array_values(array_unique([
            $this->defaultLocale,
            ...$this->enabledLocales,
        ]));
    }

    public function hasLocale(?string $locale): bool
    {
        return null !== $locale && in_array($locale, $this->getLocales(), true);
    }

    public function isRoutingEnabled(): bool
    {
        return $this->routingEnabled;
    }

    /**
     * Whether a url keeps its path whatever the language.
     */
    public function isPathExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $pattern) {
            if (preg_match('#'.$pattern.'#', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The language a request without a locale in its url is answered in: the
     * one last chosen on a page, then the one the browser asks for.
     */
    public function guessLocale(Request $request): string
    {
        $cookieLocale = $request->cookies->get(self::LOCALE_COOKIE);

        if ($this->hasLocale($cookieLocale)) {
            return $cookieLocale;
        }

        return $request->getPreferredLanguage($this->getLocales()) ?? $this->defaultLocale;
    }

    /**
     * How a language names itself: "français", "English".
     */
    public function getLocaleName(string $locale): string
    {
        return mb_convert_case(
            Languages::getName(\Locale::getPrimaryLanguage($locale), $locale),
            MB_CASE_TITLE
        );
    }
}
