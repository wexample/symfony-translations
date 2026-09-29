<?php

namespace Wexample\SymfonyTranslations\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Wexample\SymfonyTranslations\Service\LocaleService;

/**
 * Gives a locale to the requests whose url carries none, and remembers the one
 * a page was read in so that those requests follow it.
 *
 * A page url says its language; an api call, a component rendered on demand or
 * a form submission does not, and would otherwise be answered in the default
 * locale while the page around it is in another.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly UrlMatcherInterface $urlMatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the router (32), before the LocaleListener (16) which pushes
            // the request locale to the router context and to the translator.
            KernelEvents::REQUEST => ['onKernelRequest', 17],
            KernelEvents::RESPONSE => 'onKernelResponse',
            KernelEvents::EXCEPTION => ['onKernelException', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (! $this->localeService->isRoutingEnabled()
            || $request->attributes->has(LocaleService::LOCALE_ATTRIBUTE)) {
            return;
        }

        $request->setLocale($this->localeService->guessLocale($request));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->attributes->get(LocaleService::LOCALE_ATTRIBUTE);

        if (! $event->isMainRequest()
            || ! $this->localeService->isRoutingEnabled()
            || ! $this->localeService->hasLocale($locale)
            || $locale === $request->cookies->get(LocaleService::LOCALE_COOKIE)) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(LocaleService::LOCALE_COOKIE, $locale, new \DateTimeImmutable('+1 year'))
        );
    }

    /**
     * An url written before the locale prefix existed, or typed without it, is
     * sent to the same page in the visitor's language.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (! $event->isMainRequest()
            || ! $this->localeService->isRoutingEnabled()
            || ! $event->getThrowable() instanceof NotFoundHttpException
            || ! $request->isMethodSafe()
            || $this->localeService->isPathExcluded($request->getPathInfo())) {
            return;
        }

        $localizedPath = '/'.$this->localeService->guessLocale($request).$request->getPathInfo();

        if (! $this->matches($localizedPath)) {
            return;
        }

        $query = $request->getQueryString();

        $event->setResponse(new RedirectResponse(
            $request->getBasePath().$localizedPath.(null !== $query ? '?'.$query : '')
        ));
    }

    private function matches(string $path): bool
    {
        try {
            $this->urlMatcher->match($path);
        } catch (RoutingExceptionInterface) {
            return false;
        }

        return true;
    }
}
