<?php

namespace Wexample\SymfonyTranslations\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Adds a locale to framework.enabled_locales in the application's translation
 * config, the list LocaleService and the router read.
 *
 * The file is edited line by line rather than dumped again, so its comments
 * and its layout survive.
 */
class LocaleConfigService
{
    final public const string CONFIG_FILE = 'config/packages/translation.yaml';

    public function __construct(
        private readonly string $projectDir,
        private readonly LocaleService $localeService,
    ) {
    }

    /**
     * @return bool False when the locale was already enabled
     */
    public function enableLocale(string $locale): bool
    {
        $path = $this->projectDir.'/'.self::CONFIG_FILE;

        if (! is_file($path)) {
            throw new \RuntimeException('No translation config to enable the locale in: '.$path);
        }

        // Read from the file, not the container: a run enabling several locales
        // would otherwise start each time from the list it was compiled with.
        $locales = array_values(array_unique([
            $this->localeService->getDefaultLocale(),
            ...(Yaml::parseFile($path)['framework']['enabled_locales'] ?? []),
        ]));

        if (in_array($locale, $locales, true)) {
            return false;
        }

        $line = '    enabled_locales: '.Yaml::dump([...$locales, $locale], 0);
        $content = file_get_contents($path);

        if (preg_match('/^    enabled_locales:.*$/m', $content)) {
            $content = preg_replace('/^    enabled_locales:.*$/m', $line, $content, 1);
        } elseif (preg_match('/^    default_locale:.*$/m', $content)) {
            $content = preg_replace('/^(    default_locale:.*)$/m', "$1\n".$line, $content, 1);
        } else {
            $content = preg_replace('/^framework:\s*$/m', "framework:\n".$line, $content, 1);
        }

        file_put_contents($path, $content);

        return true;
    }
}
