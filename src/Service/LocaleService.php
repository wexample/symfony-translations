<?php

namespace Wexample\SymfonyTranslations\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Languages;

/**
 * The locales the application speaks, how each one names itself and which way
 * it is written.
 *
 * Two lists. The interface locales are framework.enabled_locales, the one
 * Symfony already reads: each has its urls and its translation files, and an
 * application that enables none speaks its default locale only. The content
 * locales add to them the languages entity content may be read in without an
 * interface of their own — through the api, or `translated()` given a locale —
 * and bound what is ever sent to the engine.
 */
class LocaleService
{
    final public const string LOCALE_ATTRIBUTE = '_locale';

    final public const string LOCALE_COOKIE = '_locale';

    final public const string DIRECTION_LTR = 'ltr';

    final public const string DIRECTION_RTL = 'rtl';

    /**
     * @param string[] $enabledLocales
     * @param string[] $excludedPaths
     * @param string[] $contentLocales Beyond the interface ones
     */
    public function __construct(
        private readonly string $defaultLocale,
        private readonly array $enabledLocales,
        private readonly bool $routingEnabled,
        private readonly array $excludedPaths,
        private readonly array $contentLocales = [],
        private readonly bool $cookieEnabled = false,
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

    /**
     * @return string[] framework.enabled_locales as configured, possibly empty
     */
    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    public function hasLocale(?string $locale): bool
    {
        return null !== $locale && in_array($locale, $this->getLocales(), true);
    }

    /**
     * @return string[] The interface locales, then those only content is read in
     */
    public function getContentLocales(): array
    {
        return array_values(array_unique([
            ...$this->getLocales(),
            ...$this->contentLocales,
        ]));
    }

    public function hasContentLocale(?string $locale): bool
    {
        return null !== $locale && in_array($locale, $this->getContentLocales(), true);
    }

    /**
     * Which way a language is written, from the ICU locale data rather than a
     * list kept here: `rtl` for Arabic, Hebrew, Persian, Urdu… A locale ICU
     * does not know falls back to its root, written left to right.
     */
    public function getDirection(string $locale): string
    {
        $bundle = \ResourceBundle::create($locale, 'ICUDATA', true);

        return 'right-to-left' === ($bundle['layout']['characters'] ?? null)
            ? self::DIRECTION_RTL
            : self::DIRECTION_LTR;
    }

    /**
     * The tag an html `lang` attribute expects: `en-GB`, not Symfony's `en_GB`.
     */
    public function getLanguageTag(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }

    public function isRoutingEnabled(): bool
    {
        return $this->routingEnabled;
    }

    /**
     * Whether the language is switched by `?_locale=` and kept in a cookie,
     * the urls carrying none.
     */
    public function isCookieEnabled(): bool
    {
        return $this->cookieEnabled;
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
     *
     * @param bool $content Among the content locales — an api answer — rather than the interface ones
     */
    public function guessLocale(
        Request $request,
        bool $content = false
    ): string {
        $cookieLocale = $request->cookies->get(self::LOCALE_COOKIE);

        if ($this->hasLocale($cookieLocale)) {
            return $cookieLocale;
        }

        return $request->getPreferredLanguage($content ? $this->getContentLocales() : $this->getLocales())
            ?? $this->defaultLocale;
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
