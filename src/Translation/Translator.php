<?php

namespace Wexample\SymfonyTranslations\Translation;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyDesignSystem\Helper\TemplateHelper;
use Wexample\SymfonyHelpers\Helper\FileHelper;

class Translator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    final public const string DOMAIN_SEPARATOR = ClassHelper::METHOD_SEPARATOR;

    final public const string KEYS_SEPARATOR = FileHelper::EXTENSION_SEPARATOR;

    protected array $domainsStack = [];

    private array $locales = [];

    /**
     * @throws InvalidArgumentException|Exception
     */
    public function __construct(
        public \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator,
    )
    {
    }

    public function setDomainFromPath(
        string $name,
        string $path
    ): string
    {
        $domain = Translator::buildDomainFromPath(
            TemplateHelper::trimPathPrefix($path)
        );

        $this->setDomain($name, $domain);
        return $domain;
    }

    public static function buildDomainFromPath(string $path): string
    {
        $info = (object) pathinfo($path);

        // The path format is valid.
        if ('.' !== $info->dirname) {
            return str_replace(
                    '/',
                    self::KEYS_SEPARATOR,
                    $info->dirname
                )
                . self::KEYS_SEPARATOR
                . current(
                    explode(self::KEYS_SEPARATOR, $info->basename)
                );
        } else {
            return $path;
        }
    }

    public function setDomain(
        string $name,
        string $value
    ): void
    {
        $this->domainsStack[$name][] = $value;
    }

    public function revertDomain(string $name): void
    {
        array_pop($this->domainsStack[$name]);
    }

    public function getDomainsStack(): array
    {
        return $this->domainsStack;
    }

    public function transFilter(string $key): array
    {
        $catalogue = $this->translator->getCatalogue();
        $messages = $catalogue->all();
        $regex = $this->buildRegexForFilterKey($key);
        $filtered = [];

        foreach ($messages as $domain => $translations) {
            foreach ($translations as $id => $translation) {
                if (preg_match($regex, $id)) {
                    $filtered[$domain . self::DOMAIN_SEPARATOR . $id] = $translation;
                }
            }
        }

        return $filtered;
    }

    public function buildRegexForFilterKey(string $key): string
    {
        return '/^' . str_replace('*', '.*', $key) . '$/';
    }

    public function getCatalogues(): array
    {
        return $this->translator->getCatalogues();
    }

    public function getAllLocales(): array
    {
        return array_values($this->locales);
    }

    public function setLocale($locale): void
    {
        $this->translator->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function getCatalogue($locale = null): MessageCatalogueInterface
    {
        return $this->translator->getCatalogue($locale);
    }

    public function trans(
        string $id,
        array $parameters = [],
        string $domain = null,
        string $locale = null
    ): string
    {
        return $this->translator->trans(
            id: $id,
            parameters: $parameters,
            domain: $domain,
            locale: $locale
        );
    }
}
