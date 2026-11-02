<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

use MauticPlugin\AiEmailSectionsBundle\Exception\SegmentPlanException;

final class SegmentPlanValidator
{
    private const MAX_GROUPS = 10;
    private const MAX_CONDITIONS = 30;
    private const MAX_VALUE_ITEMS = 100;

    /**
     * @param mixed[] $plan
     * @param mixed[] $catalog
     *
     * @return array{groups: array<int, array{conditions: array<int, array{object: string, field: string, operator: string, value: mixed, display?: string}>}>, summary: string}
     */
    public function validate(array $plan, array $catalog): array
    {
        $allowed = $this->allowedFields($catalog);
        $groups  = $plan['groups'] ?? null;

        if (!is_array($groups) || [] === $groups || count($groups) > self::MAX_GROUPS) {
            throw new SegmentPlanException('The generated segment has an invalid number of groups.');
        }

        $normalized = [];
        $total      = 0;

        foreach ($groups as $group) {
            $conditions = is_array($group) ? ($group['conditions'] ?? null) : null;
            if (!is_array($conditions) || [] === $conditions) {
                throw new SegmentPlanException('The generated segment contains an empty group.');
            }

            $normalizedConditions = [];
            foreach ($conditions as $condition) {
                if (!is_array($condition)) {
                    throw new SegmentPlanException('The generated segment contains an invalid condition.');
                }

                ++$total;
                if ($total > self::MAX_CONDITIONS) {
                    throw new SegmentPlanException('The generated segment contains too many conditions.');
                }

                $object   = $this->identifier($condition['object'] ?? null);
                $field    = $this->identifier($condition['field'] ?? null);
                $operator = $this->operator($condition['operator'] ?? null);
                $key      = $object."\0".$field;

                if (!isset($allowed[$key])) {
                    throw new SegmentPlanException(sprintf('The generated field %s.%s is not offered by this Mautic.', $object, $field));
                }

                $operator = $this->normalizeOperator($operator, $allowed[$key]);

                if (!in_array($operator, $allowed[$key]['values'], true)) {
                    throw new SegmentPlanException(sprintf('The generated operator %s is not offered for %s.%s.', $operator, $object, $field));
                }

                $normalizedCondition = [
                    'object'   => $object,
                    'field'    => $field,
                    'operator' => $operator,
                    'value'    => $this->value($condition['value'] ?? null),
                ];

                if (isset($condition['display']) && is_scalar($condition['display'])) {
                    $normalizedCondition['display'] = mb_substr(trim((string) $condition['display']), 0, 250);
                }

                $normalizedConditions[] = $normalizedCondition;
            }

            $normalized[] = ['conditions' => $normalizedConditions];
        }

        $summary = isset($plan['summary']) && is_scalar($plan['summary'])
            ? mb_substr(trim((string) $plan['summary']), 0, 500)
            : '';

        return ['groups' => $normalized, 'summary' => $summary];
    }

    /**
     * @param mixed[] $catalog
     *
     * @return array<string, array{values: string[], labels: array<string, string>}>
     */
    private function allowedFields(array $catalog): array
    {
        $allowed = [];

        foreach ($catalog as $field) {
            if (!is_array($field)) {
                continue;
            }

            try {
                $object = $this->identifier($field['object'] ?? null);
                $alias  = $this->identifier($field['field'] ?? null);
            } catch (SegmentPlanException) {
                continue;
            }

            $operators = [];
            $labels    = [];
            foreach (is_array($field['operators'] ?? null) ? $field['operators'] : [] as $operator) {
                if (!is_array($operator) || !isset($operator['value']) || !is_scalar($operator['value'])) {
                    continue;
                }

                $value = (string) $operator['value'];
                $operators[] = $value;
                if (isset($operator['label']) && is_scalar($operator['label'])) {
                    $labels[$this->operatorToken((string) $operator['label'])] = $value;
                }
            }

            if ([] !== $operators) {
                $allowed[$object."\0".$alias] = [
                    'values' => array_values(array_unique($operators)),
                    'labels' => $labels,
                ];
            }
        }

        return $allowed;
    }

    private function identifier(mixed $value): string
    {
        if (!is_string($value) || 1 !== preg_match('/\A[a-zA-Z0-9_.:-]{1,190}\z/', $value)) {
            throw new SegmentPlanException('The generated segment contains an invalid identifier.');
        }

        return $value;
    }

    private function operator(mixed $value): string
    {
        if (!is_string($value) || '' === $value || mb_strlen($value) > 40) {
            throw new SegmentPlanException('The generated segment contains an invalid operator.');
        }

        return $value;
    }

    /** @param array{values: string[], labels: array<string, string>} $allowed */
    private function normalizeOperator(string $operator, array $allowed): string
    {
        $aliases = [
            '>'  => 'gt',
            '>=' => 'gte',
            '<'  => 'lt',
            '<=' => 'lte',
        ];
        $normalized = $aliases[$operator] ?? $operator;

        if (in_array($normalized, $allowed['values'], true)) {
            return $normalized;
        }

        $token = $this->operatorToken($operator);

        return $allowed['labels'][$token] ?? $normalized;
    }

    private function operatorToken(string $operator): string
    {
        $token = preg_replace('/[^a-z0-9]+/iu', '', mb_strtolower(trim($operator)));

        return is_string($token) ? $token : mb_strtolower(trim($operator));
    }

    private function value(mixed $value): mixed
    {
        if (is_array($value)) {
            if (count($value) > self::MAX_VALUE_ITEMS) {
                throw new SegmentPlanException('The generated condition contains too many values.');
            }

            return array_map(fn (mixed $item): string|int|float|bool|null => $this->scalarValue($item), array_values($value));
        }

        return $this->scalarValue($value);
    }

    private function scalarValue(mixed $value): string|int|float|bool|null
    {
        if (null === $value || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (!is_string($value)) {
            throw new SegmentPlanException('The generated condition contains an invalid value.');
        }

        return mb_substr($value, 0, 2000);
    }
}
