<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Tests\Unit\Service;

use MauticPlugin\AiEmailSectionsBundle\Exception\LlmUnavailableException;
use MauticPlugin\AiEmailSectionsBundle\Service\GeminiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeminiClientTest extends TestCase
{
    public function testUsesGenerateContentShapeAndReturnsAllTextParts(): void
    {
        $seen = new \stdClass();
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($seen): MockResponse {
            $seen->method  = $method;
            $seen->url     = $url;
            $seen->headers = $options['headers'] ?? [];
            $seen->body    = $options['body'] ?? '';

            return new MockResponse(json_encode([
                'candidates' => [['content' => ['parts' => [
                    ['text' => '<mj-section>'],
                    ['text' => '</mj-section>'],
                ]]]],
            ], JSON_THROW_ON_ERROR));
        });

        $result = $this->client($http)->complete([
            ['role' => 'system', 'content' => 'You write MJML.'],
            ['role' => 'user', 'content' => 'Example input'],
            ['role' => 'assistant', 'content' => 'Example output'],
            ['role' => 'user', 'content' => 'Create a hero'],
        ]);

        $this->assertSame('<mj-section></mj-section>', $result);
        $this->assertSame('POST', $seen->method);
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', $seen->url);

        $headers = strtolower(implode("\n", $seen->headers));
        $this->assertStringContainsString('x-goog-api-key: google-key', $headers);

        $payload = json_decode((string) $seen->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('You write MJML.', $payload['systemInstruction']['parts'][0]['text']);
        $this->assertSame(['user', 'model', 'user'], array_column($payload['contents'], 'role'));
        $this->assertSame(0.4, $payload['generationConfig']['temperature']);
        $this->assertSame(1500, $payload['generationConfig']['maxOutputTokens']);
    }

    public function testRejectsAResponseWithoutText(): void
    {
        $http = new MockHttpClient(new MockResponse('{"candidates":[]}'));

        $this->expectException(LlmUnavailableException::class);
        $this->client($http)->complete([['role' => 'user', 'content' => 'hello']]);
    }

    public function testRequestsStructuredJsonWithoutChangingNormalCompletions(): void
    {
        $seen = new \stdClass();
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($seen): MockResponse {
            $seen->body = $options['body'] ?? '';

            return new MockResponse('{"candidates":[{"content":{"parts":[{"text":"{\\"groups\\":[]}"}]}}]}');
        });

        $this->assertSame(
            '{"groups":[]}',
            $this->client($http)->completeJson([['role' => 'user', 'content' => 'Create a segment plan']])
        );

        $payload = json_decode((string) $seen->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('application/json', $payload['generationConfig']['responseMimeType']);
        $this->assertSame(4096, $payload['generationConfig']['maxOutputTokens']);
    }

    private function client(MockHttpClient $http): GeminiClient
    {
        return new GeminiClient(
            $http,
            'https://generativelanguage.googleapis.com/v1beta',
            'google-key',
            'gemini-2.5-flash',
            0.4,
            1500,
            45,
        );
    }
}
