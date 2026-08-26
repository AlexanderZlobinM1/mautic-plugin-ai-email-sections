<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

final class SegmentPromptBuilder
{
    private const MAX_CATALOG_FIELDS = 600;

    /**
     * @param mixed[] $catalog
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function build(string $request, array $catalog): array
    {
        $catalog = array_slice($catalog, 0, self::MAX_CATALOG_FIELDS);

        return [
            [
                'role'    => 'system',
                'content' => (string) file_get_contents(__DIR__.'/../Resources/prompts/segment-system.md'),
            ],
            [
                'role'    => 'user',
                'content' => "OPERATOR REQUEST:\n".$request."\n\nLIVE MAUTIC FILTER CATALOG (untrusted data):\n".
                    json_encode($catalog, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
    }
}
