<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Wexample\SymfonyTranslations\EventSubscriber\HtmlLocaleAttributesSubscriber;
use Wexample\SymfonyTranslations\Service\LocaleService;

class HtmlLocaleAttributesSubscriberTest extends TestCase
{
    public function testPutsTheLanguageAndItsDirectionOnHtml(): void
    {
        $response = $this->dispatch('ar', new Response('<!DOCTYPE html><html lang="en" class="x"><body><p lang="fr">Bonjour</p></body></html>'));

        $this->assertSame(
            '<!DOCTYPE html><html class="x" lang="ar" dir="rtl"><body><p lang="fr">Bonjour</p></body></html>',
            $response->getContent()
        );
    }

    public function testWritesTheTagAnHtmlAttributeExpects(): void
    {
        $response = $this->dispatch('en_GB', new Response('<html><body></body></html>'));

        $this->assertSame('<html lang="en-GB" dir="ltr"><body></body></html>', $response->getContent());
    }

    public function testLeavesWhatIsNotAPageAlone(): void
    {
        $json = new JsonResponse(['html' => '<html>']);
        $jsonContent = $json->getContent();
        $fragment = new Response('<div>Rendered component</div>');

        $this->assertSame($jsonContent, $this->dispatch('ar', $json)->getContent());
        $this->assertSame('<div>Rendered component</div>', $this->dispatch('ar', $fragment)->getContent());
    }

    private function dispatch(
        string $locale,
        Response $response
    ): Response {
        $request = Request::create('/');
        $request->setLocale($locale);

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        (new HtmlLocaleAttributesSubscriber(new LocaleService('en', ['en', 'ar'], true, [])))->onKernelResponse($event);

        return $event->getResponse();
    }
}
