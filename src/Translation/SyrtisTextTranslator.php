<?php

namespace Wexample\SymfonyTranslations\Translation;

use SyrtisClient\Common\SyrtisClient;
use SyrtisClient\Entity\Message;
use SyrtisClient\Entity\Session;
use SyrtisClient\Repository\SessionRepository;
use Wexample\SymfonyTranslations\Exception\TranslationReplyException;
use Wexample\SymfonyTranslations\Interface\TextTranslatorInterface;

/**
 * Translates through a session on a Syrtis translation scenario, a batch of
 * texts per request. The scenario receives, stamped `translate`:
 *
 * - a `LANG_CONFIG` message, `{"source": "fr", "target": "en_GB"}`: Symfony
 *   locale identifiers, as they are, never converted;
 * - a conversation message holding the batch, a JSON object of texts by key.
 *
 * It answers with a conversation message holding a JSON object of the
 * translations under the same keys, leaving `[#n]` placeholders untouched.
 * Batches are cut to fit `max_batch_length` characters of texts, so that the
 * model's answer stays within its output.
 */
class SyrtisTextTranslator implements TextTranslatorInterface
{
    final public const string ENGINE_NAME = 'syrtis';

    final public const string MESSAGE_NAME_LANG_CONFIG = 'LANG_CONFIG';

    final public const string STAMP_TRANSLATE = 'translate';

    public function __construct(
        private readonly SyrtisClient $client,
        private readonly string $sessionSecureId,
        private readonly int $maxBatchLength,
    ) {
    }

    public function translate(
        array $texts,
        string $sourceLocale,
        string $targetLocale
    ): array {
        $translations = [];

        foreach ($this->buildBatches($texts) as $batch) {
            foreach ($this->translateBatch($batch, $sourceLocale, $targetLocale) as $key => $translation) {
                $translations[$key] = $translation;
            }
        }

        return $translations;
    }

    public function getEngineName(): string
    {
        return self::ENGINE_NAME;
    }

    /**
     * @param array<array-key, string> $texts
     * @return array<array-key, string>[] A text longer than the limit makes a batch of its own.
     */
    private function buildBatches(array $texts): array
    {
        $batches = [];
        $batch = [];
        $length = 0;

        foreach ($texts as $key => $text) {
            $textLength = mb_strlen($text);

            if ([] !== $batch && $length + $textLength > $this->maxBatchLength) {
                $batches[] = $batch;
                $batch = [];
                $length = 0;
            }

            $batch[$key] = $text;
            $length += $textLength;
        }

        if ([] !== $batch) {
            $batches[] = $batch;
        }

        return $batches;
    }

    /**
     * @param array<array-key, string> $batch
     * @return array<array-key, string>
     */
    private function translateBatch(
        array $batch,
        string $sourceLocale,
        string $targetLocale
    ): array {
        /** @var SessionRepository $sessions */
        $sessions = $this->client->getRepository(Session::class);

        $messages = $sessions->sendMessage(
            $this->sessionSecureId,
            messages: [
                [
                    'content' => json_encode(['source' => $sourceLocale, 'target' => $targetLocale], JSON_THROW_ON_ERROR),
                    'contentType' => Message::CONTENT_TYPE_DEFAULT,
                    'format' => Message::FORMAT_JSON,
                    'name' => self::MESSAGE_NAME_LANG_CONFIG,
                    'stamps' => [self::STAMP_TRANSLATE],
                ],
                [
                    // Keys as strings, so that a list is sent as an object too.
                    'content' => json_encode((object) $batch, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'contentType' => Message::CONTENT_TYPE_CONVERSATION,
                    'format' => Message::FORMAT_JSON,
                    'stamps' => [self::STAMP_TRANSLATE],
                ],
            ],
            sync: true,
        );

        $reply = $this->findReply($messages);
        $decoded = json_decode((string) $reply->getContent(), true);

        if (! is_array($decoded)) {
            throw new TranslationReplyException(
                'The translation scenario did not answer a JSON object: '.mb_substr((string) $reply->getContent(), 0, 200)
            );
        }

        $missing = array_diff(array_map('strval', array_keys($batch)), array_map('strval', array_keys($decoded)));
        if ([] !== $missing) {
            throw new TranslationReplyException(
                'The translation scenario left keys out: '.implode(', ', $missing)
            );
        }

        $translations = [];
        foreach (array_keys($batch) as $key) {
            $translations[$key] = (string) $decoded[(string) $key];
        }

        return $translations;
    }

    /**
     * @param Message[] $messages
     */
    private function findReply(array $messages): Message
    {
        foreach ($messages as $message) {
            if ($message->isReply()) {
                return $message;
            }
        }

        throw new TranslationReplyException('The translation scenario gave no reply.');
    }
}
