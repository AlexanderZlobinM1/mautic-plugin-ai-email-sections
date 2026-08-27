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
        $catalog = $this->contextualCatalog($request, $catalog);

        if (count($catalog) <= self::MAX_CATALOG_FIELDS) {
            return $catalog;
        }

        $terms = $this->terms($request);
        $requestText = $this->normalise($request);
        $ranked = [];

        foreach ($catalog as $position => $field) {
            if (!is_array($field)) {
                continue;
            }

            $label  = $this->normalise((string) ($field['label'] ?? ''));
            $alias  = $this->normalise((string) ($field['field'] ?? ''));
            $object = $this->normalise((string) ($field['object'] ?? ''));
            $score  = 0;

            if ('' !== $label && str_contains($requestText, $label)) {
                $score += 40;
            }

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

    /**
     * Remove attractive but semantically incompatible alternatives before a
     * small model sees the catalog. The complete catalog is still retained by
     * SegmentAssistant for final validation.
     *
     * @param mixed[] $catalog
     *
     * @return mixed[]
     */
    private function contextualCatalog(string $request, array $catalog): array
    {
        $text = $this->normalise($request);
        $pageActivity = 1 === preg_match(
            '/(?:open|view|visit|browse|access)\w*\s+(?:the\s+)?(?:site|website|page|url)|(?:откр|просмотр|посет)\w*\s+(?:сайт|страниц|url)|(?:otvor|pregled|poset)\w*\s+(?:sajt|stranic|url)/u',
            $text
        );
        $hasTimeWindow = 1 === preg_match(
            '/(?:last|past|previous)\s+\d+\s+days?|(?:today|yesterday)|(?:последн|прошл)\w*\s+\d+\s+(?:дн|день)|(?:сегодня|вчера)|(?:poslednj|prethodn)\w*\s+\d+\s+dan|(?:danas|juče|juce)/u',
            $text
        );
        $contactFirstName = 1 === preg_match('/\bfirst\s+name\b|\bим(?:я|ени)\b|\bime\b/u', $text);
        $companyRequested = 1 === preg_match('/\bcompan(?:y|ies)\b|компан|kompan/u', $text);

        return array_values(array_filter($catalog, function (mixed $entry) use ($pageActivity, $hasTimeWindow, $contactFirstName, $companyRequested): bool {
            if (!is_array($entry)) {
                return false;
            }

            $object = $this->normalise((string) ($entry['object'] ?? ''));
            $field  = $this->normalise((string) ($entry['field'] ?? ''));

            if ($pageActivity && !$hasTimeWindow && 1 === preg_match('/(?:^|_)(?:in_last_\d+_days|from_yesterday)$/', $field)) {
                return false;
            }

            if ($pageActivity && 'company' === $object && 'companywebsite' === $field) {
                return false;
            }

            if ($contactFirstName && !$companyRequested && 'company' === $object && 'companyname' === $field) {
                return false;
            }

            return true;
        }));
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
            '/(?:open|view|visit|browse|access)\w*\s+(?:the\s+)?(?:site|website|page|url)|(?:откр|просмотр|посет)\w*\s+(?:сайт|страниц|url)|(?:otvor|pregled|poset)\w*\s+(?:sajt|stranic|url)/u' => ['page', 'url', 'visit'],
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
