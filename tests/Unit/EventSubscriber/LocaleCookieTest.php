<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Wexample\SymfonyTranslations\EventSubscriber\LocaleSubscriber;
use Wexample\SymfonyTranslations\Service\LocaleService;

/**
 * Without a prefix (locale_cookie): `?_locale=` chooses, the cookie keeps the
 * choice, the browser speaks when nothing was chosen.
 */
class LocaleCookieTest extends TestCase
{
    public function testTheQueryChoosesAndTheCookieKeepsIt(): void
    {
        $request = Request::create('/dashboard', parameters: ['_locale' => 'en']);

        $this->request($request);
        $this->assertSame('en', $request->getLocale());
        $this->assertSame('en', $request->attributes->get('_locale'));

        $cookies = $this->response($request)->headers->getCookies();
        $this->assertSame('_locale', $cookies[0]->getName());
        $this->assertSame('en', $cookies[0]->getValue());
    }

    public function testTheCookieThenTheBrowserSpeakWhenNothingIsChosen(): void
    {
        $chosen = Request::create('/dashboard', cookies: ['_locale' => 'en'], server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        $this->request($chosen);
        $this->assertSame('en', $chosen->getLocale());
        $this->assertFalse($chosen->attributes->has('_locale'));
        $this->assertSame([], $this->response($chosen)->headers->getCookies());

        $browser = Request::create('/dashboard', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.9']);
        $this->request($browser);
        $this->assertSame('en', $browser->getLocale());
    }

    public function testALanguageNotSpokenIsNoChoice(): void
    {
        $request = Request::create('/dashboard', parameters: ['_locale' => 'xx'], server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $this->request($request);
        $this->assertSame('fr', $request->getLocale());
        $this->assertFalse($request->attributes->has('_locale'));
    }

    private function subscriber(): LocaleSubscriber
    {
        return new LocaleSubscriber(
            new LocaleService('fr', ['fr', 'en'], false, [], cookieEnabled: true),
            $this->createStub(UrlMatcherInterface::class)
        );
    }

    private function request(Request $request): void
    {
        $this->subscriber()->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    private function response(Request $request): Response
    {
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
        $this->subscriber()->onKernelResponse($event);

        return $event->getResponse();
    }
}
