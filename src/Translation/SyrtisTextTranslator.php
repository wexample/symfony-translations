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
 * - a `LANG_CONFIG` message, `{"source": "fr", "target": "en_GB", "source_name":
 *   "French", "target_name": "English (United Kingdom)"}`: the Symfony locale
 *   identifiers as they are, and their English names from the ICU data — what
 *   a prompt says to the model, with no table of languages to keep there;
 * - a conversation message holding the batch, a JSON object of texts by key.
 *
 * It answers with a conversation message holding a JSON object of the
 * translations under the same keys, leaving `[#n]` placeholders untouched.
 * A text already written in the target language comes back as `__SAME__`
 * ("Salut" asked in French): the whole reply for the batch, or the value of
 * one of its keys. The source text is then kept as its translation.
 * Batches are cut to fit `max_batch_length` characters of texts, so that the
 * model's answer stays within its output.
 *
 * A model now and then skips a key of a long batch, or answers plain text. The
 * keys left out are asked once more, on their own; those still missing are left
 * out of the result, for the caller to ask again later rather than lose the
 * whole batch. A single text answered as plain text is taken as its translation.
 */
class SyrtisTextTranslator implements TextTranslatorInterface
{
    final public const string ENGINE_NAME = 'syrtis';

    final public const string MESSAGE_NAME_LANG_CONFIG = 'LANG_CONFIG';

    final public const string STAMP_TRANSLATE = 'translate';

    final public const string REPLY_SAME = '__SAME__';

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
     * @return array<array-key, string> Without the keys left out twice
     */
    private function translateBatch(
        array $batch,
        string $sourceLocale,
        string $targetLocale
    ): array {
        $translations = $this->requestTranslations($batch, $sourceLocale, $targetLocale);
        $missing = array_diff_key($batch, $translations);

        if ([] !== $missing) {
            $translations += $this->requestTranslations($missing, $sourceLocale, $targetLocale);
        }

        return $translations;
    }

    /**
     * @param array<array-key, string> $batch
     * @return array<array-key, string> The keys of $batch the reply holds
     */
    private function requestTranslations(
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
                    'content' => json_encode([
                        'source' => $sourceLocale,
                        'target' => $targetLocale,
                        'source_name' => \Locale::getDisplayName($sourceLocale, 'en'),
                        'target_name' => \Locale::getDisplayName($targetLocale, 'en'),
                    ], JSON_THROW_ON_ERROR),
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

        $content = trim((string) $this->findReply($messages)->getContent());

        if (self::REPLY_SAME === $content) {
            return $batch;
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            // A model asked for one text now and then answers the translated
            // text alone, without its JSON wrapper. Of several, nothing can be
            // told apart: all are left out, to be asked again.
            return 1 === count($batch) ? [array_key_first($batch) => $content] : [];
        }

        $translations = [];
        foreach (array_keys($batch) as $key) {
            if (! array_key_exists((string) $key, $decoded)) {
                continue;
            }

            $translation = (string) $decoded[(string) $key];
            $translations[$key] = self::REPLY_SAME === trim($translation) ? $batch[$key] : $translation;
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
