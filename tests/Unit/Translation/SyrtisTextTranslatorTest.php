<?php

namespace Wexample\SymfonyTranslations\Tests\Unit\Translation;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SyrtisClient\Common\SyrtisClient;
use Wexample\SymfonyTranslations\Translation\SyrtisTextTranslator;

class SyrtisTextTranslatorTest extends TestCase
{
    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    public function testTranslatesABatchUnderTheSameKeys(): void
    {
        $translator = $this->createTranslator([
            $this->reply(['title' => 'Hello [#0]', 3 => 'Your order is ready.']),
        ]);

        $translations = $translator->translate(
            ['title' => 'Bonjour [#0]', 3 => 'Votre commande est prête.'],
            'fr',
            'en_GB'
        );

        $this->assertSame(['title' => 'Hello [#0]', 3 => 'Your order is ready.'], $translations);

        [$config, $texts] = $this->getSentMessages(0);
        $this->assertSame('LANG_CONFIG', $config['name']);
        $this->assertSame(
            ['source' => 'fr', 'target' => 'en_GB', 'source_name' => 'French', 'target_name' => 'English (United Kingdom)'],
            json_decode($config['content'], true)
        );
        $this->assertSame(['translate'], $texts['stamps']);
        $this->assertSame(
            ['title' => 'Bonjour [#0]', '3' => 'Votre commande est prête.'],
            json_decode($texts['content'], true)
        );
    }

    public function testCutsBatchesToTheirMaximumLength(): void
    {
        $translator = $this->createTranslator(
            [
                $this->reply(['a' => 'A', 'b' => 'B']),
                $this->reply(['c' => 'C']),
            ],
            maxBatchLength: 10
        );

        $translations = $translator->translate(['a' => 'aaaa', 'b' => 'bbbb', 'c' => 'cccc'], 'fr', 'en');

        $this->assertSame(['a' => 'A', 'b' => 'B', 'c' => 'C'], $translations);
        $this->assertCount(2, $this->history);
    }

    public function testKeepsTheSourceOfABatchAnsweredAsTheSame(): void
    {
        $translator = $this->createTranslator([$this->reply('__SAME__')]);

        $this->assertSame(
            ['greeting' => 'Salut'],
            $translator->translate(['greeting' => 'Salut'], 'en', 'fr')
        );
    }

    public function testKeepsTheSourceOfAKeyAnsweredAsTheSame(): void
    {
        $translator = $this->createTranslator([
            $this->reply(['greeting' => '__SAME__', 'thanks' => 'Merci']),
        ]);

        $this->assertSame(
            ['greeting' => 'Salut', 'thanks' => 'Merci'],
            $translator->translate(['greeting' => 'Salut', 'thanks' => 'Thanks'], 'en', 'fr')
        );
    }

    public function testAsksAgainForTheKeysLeftOut(): void
    {
        $translator = $this->createTranslator([
            $this->reply(['a' => 'A']),
            $this->reply(['b' => 'B']),
        ]);

        $this->assertSame(['a' => 'A', 'b' => 'B'], $translator->translate(['a' => 'a', 'b' => 'b'], 'fr', 'en'));
        $this->assertSame(['b' => 'b'], json_decode($this->getSentMessages(1)[1]['content'], true));
    }

    public function testLeavesOutAKeyMissingTwice(): void
    {
        $translator = $this->createTranslator([
            $this->reply(['a' => 'A']),
            $this->reply([]),
        ]);

        $this->assertSame(['a' => 'A'], $translator->translate(['a' => 'a', 'b' => 'b'], 'fr', 'en'));
        $this->assertCount(2, $this->history);
    }

    public function testTakesThePlainTextAnswerToASingleText(): void
    {
        $translator = $this->createTranslator([$this->reply('Cette page liste les routes.')]);

        $this->assertSame(
            ['intro' => 'Cette page liste les routes.'],
            $translator->translate(['intro' => 'This page lists the routes.'], 'en', 'fr')
        );
    }

    public function testLeavesOutABatchAnsweredInPlainText(): void
    {
        $translator = $this->createTranslator([
            $this->reply('Deux phrases mêlées.'),
            $this->reply(['a' => 'A', 'b' => 'B']),
        ]);

        // Asked once more, on their own: the second answer is kept.
        $this->assertSame(['a' => 'A', 'b' => 'B'], $translator->translate(['a' => 'a', 'b' => 'b'], 'fr', 'en'));
    }

    /**
     * @param Response[] $responses
     */
    private function createTranslator(
        array $responses,
        int $maxBatchLength = 6000
    ): SyrtisTextTranslator {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SyrtisTextTranslator(
            new SyrtisClient(
                host: 'https://syrtis.test',
                apiKey: 'test-key',
                httpClient: new Client(['handler' => $stack]),
            ),
            'ses_test',
            $maxBatchLength
        );
    }

    /**
     * The sync answer of the API: the request's messages, the reply last.
     *
     * @param array<array-key, string>|string $translations The translations, or the raw reply
     */
    private function reply(array|string $translations): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'type' => 'success',
            'code' => 200,
            'data' => [
                'messages' => [
                    [
                        'type' => 'message',
                        'entity' => [
                            'secureId' => 'mes_reply',
                            'content' => is_string($translations) ? $translations : json_encode((object) $translations),
                            'contentType' => 'conversation',
                            'format' => 'text',
                            'name' => null,
                            'origin' => 'node',
                        ],
                        'metadata' => [],
                        'relationships' => [],
                    ],
                ],
            ],
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSentMessages(int $requestIndex): array
    {
        $body = (string) $this->history[$requestIndex]['request']->getBody();

        // Multipart: the whole payload travels as JSON in a "data" field.
        preg_match('/name="data"\r\n(?:[^\r\n]+\r\n)*\r\n(.*?)\r\n--/s', $body, $match);

        return json_decode($match[1], true)['messages'];
    }
}
