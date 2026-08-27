<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

use MauticPlugin\AiEmailSectionsBundle\Exception\SegmentPlanException;

final class SegmentAssistant
{
    public function __construct(
        private readonly GeneratorFactory $generatorFactory,
        private readonly SegmentPromptBuilder $promptBuilder,
        private readonly SegmentPlanValidator $validator,
        private readonly SegmentPlanLogicNormalizer $logicNormalizer,
    ) {
    }

    /**
     * @param mixed[] $catalog
     *
     * @return array{groups: mixed[], summary: string}
     */
    public function generate(string $request, array $catalog): array
    {
        $client   = $this->generatorFactory->createClient();
        $messages = $this->promptBuilder->build($request, $catalog);
        $lastError = null;

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $raw = $client instanceof JsonLlmClientInterface
                ? $client->completeJson($messages)
                : $client->complete($messages);

            try {
                return $this->decodeAndValidate($request, $raw, $catalog);
            } catch (SegmentPlanException $exception) {
                $lastError = $exception;
                if (1 === $attempt) {
                    break;
                }

                $messages[] = ['role' => 'assistant', 'content' => $raw];
                $messages[] = [
                    'role'    => 'user',
                    'content' => sprintf(
                        'Your response was rejected: %s Return one corrected JSON object only. Use only fields and operators from the supplied catalog.',
                        $exception->getMessage()
                    ),
                ];
            }
        }

        throw $lastError ?? new SegmentPlanException('The model did not return a segment plan.');
    }

    /**
     * @param mixed[] $catalog
     *
     * @return array{groups: mixed[], summary: string}
     */
    private function decodeAndValidate(string $request, string $raw, array $catalog): array
    {
        $json = $this->extractJson($raw);

        try {
            $plan = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new SegmentPlanException('The model did not return a JSON segment plan.', 0, $exception);
        }

        if (!is_array($plan)) {
            throw new SegmentPlanException('The model did not return a segment plan.');
        }

        return $this->validator->validate($this->logicNormalizer->normalize($request, $plan), $catalog);
    }

    private function extractJson(string $raw): string
    {
        $trimmed = trim($raw);
        if (preg_match('/\A```(?:json)?\s*(.*?)\s*```\z/is', $trimmed, $matches)) {
            $trimmed = trim($matches[1]);
        }

        $start = strpos($trimmed, '{');
        $end   = strrpos($trimmed, '}');
        if (false === $start || false === $end || $end < $start) {
            throw new SegmentPlanException('The model did not return JSON: '.mb_substr($trimmed, 0, 500));
        }

        return substr($trimmed, $start, $end - $start + 1);
    }
}
