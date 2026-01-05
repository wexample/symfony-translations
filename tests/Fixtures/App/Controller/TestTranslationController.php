<?php

namespace Wexample\SymfonyTranslations\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

final class TestTranslationController
{
    #[Route('/_test/translations', name: 'symfony_translations_test_page')]
    public function page(Environment $twig): Response
    {
        return new Response($twig->render('page.html.twig'));
    }
}
