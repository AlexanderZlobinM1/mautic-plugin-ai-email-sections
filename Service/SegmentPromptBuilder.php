<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

final class SegmentPromptBuilder
{
    private const MAX_CATALOG_FIELDS = 8;

    private const STOP_WORDS = [
        'and', 'create', 'for', 'from', 'in', 'of', 'on', 'or', 'segment',
        'start', 'starting', 'starts', 'that', 'the', 'with', 'whose',
        'без', 'для', 'или', 'из', 'которые', 'сегмент', 'создай', 'что',
        'bez', 'da', 'ili', 'koji', 'koje', 'napravi', 'od', 'sa', 'segment', 'za',
    ];

    /**
     * @param mixed[] $catalog
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function build(string $request, array $catalog): array
    {
        $catalog = $this->relevantCatalog($request, $catalog);

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

    /**
     * Small local models lose both time and accuracy when every Mautic field is
     * placed in front of them. Rank the prompt catalog by the operator's own
     * words while the validator still checks the answer against the complete
     * live catalog supplied to SegmentAssistant.
     *
     * @param mixed[] $catalog
     *
     * @return mixed[]
     */
    private function relevantCatalog(string $request, array $catalog): array
    {
        if (count($catalog) <= self::MAX_CATALOG_FIELDS) {
            return $catalog;
        }

        $terms = $this->terms($request);
        $ranked = [];

        foreach ($catalog as $position => $field) {
            if (!is_array($field)) {
                continue;
            }

            $label  = $this->normalise((string) ($field['label'] ?? ''));
            $alias  = $this->normalise((string) ($field['field'] ?? ''));
            $object = $this->normalise((string) ($field['object'] ?? ''));
            $score  = 0;

            foreach ($terms as $term) {
                if (str_contains($label, $term)) {
                    $score += 8;
                }
                if (str_contains($alias, $term)) {
                    $score += 5;
                }
                if (str_contains($object, $term)) {
                    $score += 3;
                }
            }

            $ranked[] = ['score' => $score, 'position' => $position, 'field' => $field];
        }

        usort($ranked, static function (array $left, array $right): int {
            return $right['score'] <=> $left['score'] ?: $left['position'] <=> $right['position'];
        });

        return array_values(array_map(
            static fn (array $entry): array => $entry['field'],
            array_slice($ranked, 0, self::MAX_CATALOG_FIELDS)
        ));
    }

    /** @return string[] */
    private function terms(string $request): array
    {
        $normalised = $this->normalise($request);
        preg_match_all('/[\p{L}\p{N}]{2,}/u', $normalised, $matches);

        $terms = [];
        foreach ($matches[0] ?? [] as $term) {
            $terms[] = $term;
            if (strlen($term) > 3 && str_ends_with($term, 's')) {
                $terms[] = substr($term, 0, -1);
            }
        }

        $terms = array_values(array_diff(array_unique($terms), self::STOP_WORDS));

        $concepts = [
            '/company|компан|kompan/u' => ['company', 'name'],
            '/page|url|страниц|stranic|посет|poset/u' => ['page', 'url', 'visit'],
            '/email|mail|письм|мејл|mejl|poruk/u' => ['email', 'sent', 'read'],
            '/campaign|кампан|kampan/u' => ['campaign'],
            '/android|андроид|device|устройств|uređaj|uredjaj/u' => ['android', 'device', 'os'],
            '/date|day|день|дней|дат|dan|dana/u' => ['date'],
            '/owner|владел|vlasnik/u' => ['owner'],
            '/tag|тег|oznaka/u' => ['tag'],
            '/import|импорт|uvoz/u' => ['import'],
        ];
        foreach ($concepts as $pattern => $expansion) {
            if (preg_match($pattern, $normalised)) {
                array_push($terms, ...$expansion);
            }
        }

        return array_values(array_unique($terms));
    }

    private function normalise(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/u', '$1 $2', $value) ?? $value;

        return mb_strtolower($value);
    }
}
