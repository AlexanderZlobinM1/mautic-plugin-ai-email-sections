<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

final class SegmentPlanLogicNormalizer
{
    /**
     * Small models sometimes split an AND request into separate OR groups.
     * Merging is safe only when the operator did not request a disjunction.
     *
     * @param mixed[] $plan
     *
     * @return mixed[]
     */
    public function normalize(string $request, array $plan): array
    {
        $groups = $plan['groups'] ?? null;
        if (!is_array($groups) || count($groups) < 2 || $this->hasExplicitOr($request)) {
            return $plan;
        }

        $conditions = [];
        foreach ($groups as $group) {
            if (!is_array($group) || !is_array($group['conditions'] ?? null)) {
                return $plan;
            }
            array_push($conditions, ...$group['conditions']);
        }

        $plan['groups'] = [['conditions' => $conditions]];

        return $plan;
    }

    private function hasExplicitOr(string $request): bool
    {
        return 1 === preg_match('/\b(?:or|either|или|либо|ili)\b/ui', $request);
    }
}
