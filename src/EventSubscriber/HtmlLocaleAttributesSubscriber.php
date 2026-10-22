<?php

namespace Wexample\SymfonyTranslations\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wexample\SymfonyTranslations\Service\LocaleService;

/**
 * Puts the language of the request and its writing direction on the `<html>`
 * of every page: `lang="ar" dir="rtl"`.
 *
 * Done on the response rather than in a template, so that every page carries
 * it whichever layout drew it, and no layout has to know which languages are
 * written from right to left. The values it finds there are replaced, as the
 * request locale is the one the page was translated in.
 */
final class HtmlLocaleAttributesSubscriber implements EventSubscriberInterface
{
    private const string HTML_TAG_PATTERN = '/<html\b([^>]*)>/i';

    private const string LOCALE_ATTRIBUTES_PATTERN = '/\s(?:lang|dir)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';

    public function __construct(
        private readonly LocaleService $localeService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -16],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (! $event->isMainRequest()
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || ! str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'html')) {
            return;
        }

        $content = $response->getContent();

        if (false === $content || ! preg_match(self::HTML_TAG_PATTERN, $content)) {
            return;
        }

        $locale = $event->getRequest()->getLocale();
        $attributes = sprintf(
            ' lang="%s" dir="%s"',
            htmlspecialchars($this->localeService->getLanguageTag($locale), ENT_QUOTES),
            $this->localeService->getDirection($locale)
        );

        $response->setContent(preg_replace_callback(
            self::HTML_TAG_PATTERN,
            static fn (array $match): string => '<html'
                .preg_replace(self::LOCALE_ATTRIBUTES_PATTERN, '', $match[1])
                .$attributes.'>',
            $content,
            1
        ));
    }
}
