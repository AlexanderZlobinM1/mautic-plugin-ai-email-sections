<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

use MauticPlugin\AiEmailSectionsBundle\Exception\LlmUnavailableException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Google Gemini generateContent client. */
final class GeminiClient implements LlmClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly float $temperature,
        private readonly int $maxTokens,
        private readonly int $timeoutSeconds,
    ) {
    }

    public function complete(array $messages): string
    {
        [$system, $contents] = $this->convertMessages($messages);
        $payload             = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => $this->temperature,
                'maxOutputTokens' => $this->maxTokens,
            ],
        ];

        if ('' !== $system) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        try {
            $response = $this->http->request('POST', $this->endpoint(), [
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ],
                'json'         => $payload,
                'timeout'      => $this->timeoutSeconds,
                'max_duration' => $this->timeoutSeconds,
            ]);

            $status = $response->getStatusCode();
            $body   = $response->getContent(false);
        } catch (TransportException $exception) {
            throw LlmUnavailableException::unreachable($exception->getMessage());
        } catch (ExceptionInterface $exception) {
            throw LlmUnavailableException::unreachable($exception->getMessage());
        }

        if ($status < 200 || $status >= 300) {
            throw LlmUnavailableException::unreachable(sprintf('the provider answered %d', $status));
        }

        return $this->extractContent($body);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array{0: string, 1: array<int, array{role: string, parts: array<int, array{text: string}>}>}
     */
    private function convertMessages(array $messages): array
    {
        $system   = [];
        $contents = [];

        foreach ($messages as $message) {
            if ('system' === $message['role']) {
                $system[] = $message['content'];
                continue;
            }

            $contents[] = [
                'role'  => 'assistant' === $message['role'] ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        return [implode("\n\n", $system), $contents];
    }

    private function endpoint(): string
    {
        return sprintf(
            '%s/models/%s:generateContent',
            rtrim($this->baseUrl, '/'),
            rawurlencode($this->model),
        );
    }

    private function extractContent(string $body): string
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw LlmUnavailableException::unreachable('the response was not valid JSON');
        }

        $text = '';
        foreach ($decoded['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        if ('' === trim($text)) {
            throw LlmUnavailableException::unreachable('the response carried no content');
        }

        return $text;
    }
}
