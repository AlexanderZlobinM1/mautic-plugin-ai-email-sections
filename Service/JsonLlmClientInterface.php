<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

interface JsonLlmClientInterface extends LlmClientInterface
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function completeJson(array $messages): string;
}
