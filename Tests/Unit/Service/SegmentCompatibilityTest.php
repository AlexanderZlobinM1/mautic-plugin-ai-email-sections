<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Tests\Unit\Service;

use MauticPlugin\AiEmailSectionsBundle\Service\SegmentPlanValidator;
use MauticPlugin\AiEmailSectionsBundle\Service\SegmentPromptBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Mautic 6 and 7 do not promise identical segment aliases or operator values.
 * These fixtures keep the prompt and validator honest without pretending that
 * one version's catalog is universal.
 */
final class SegmentCompatibilityTest extends TestCase
{
    /** @dataProvider mauticCatalogs */
    public function testSmallModelStyleAnswersAreMappedToTheLiveCatalog(array $catalog, string $companyObject, string $companyField): void
    {
        $messages = (new SegmentPromptBuilder())->build(
            'Contacts whose company name starts with A and visited apple.com',
            $catalog
        );

        $this->assertStringContainsString($companyField, $messages[1]['content']);
        $this->assertStringContainsString('Mautic 6 and Mautic 7', $messages[0]['content']);

        $plan = [
            'groups' => [[
                'conditions' => [
                    [
                        'object'   => $companyObject,
                        'field'    => $companyField,
                        'operator' => 'starts with',
                        'value'    => 'A',
                    ],
                    [
                        'object'   => 'behaviors',
                        'field'    => 'hit_url',
                        'operator' => 'contains',
                        'value'    => 'apple.com',
                    ],
                ],
            ]],
        ];

        $normalized = (new SegmentPlanValidator())->validate($plan, $catalog);

        $this->assertSame('startsWith', $normalized['groups'][0]['conditions'][0]['operator']);
        $this->assertSame('contains', $normalized['groups'][0]['conditions'][1]['operator']);
    }

    /** @return iterable<string, array{array<int, array<string, mixed>>, string, string}> */
    public static function mauticCatalogs(): iterable
    {
        yield 'Mautic 6 company object' => [[
            [
                'object' => 'company',
                'field' => 'companyname',
                'label' => 'Company Name',
                'operators' => [['label' => 'starts with', 'value' => 'startsWith']],
            ],
            [
                'object' => 'behaviors',
                'field' => 'hit_url',
                'label' => 'Visited URL',
                'operators' => [['label' => 'contains', 'value' => 'contains']],
            ],
        ], 'company', 'companyname'];

        yield 'Mautic 7 lead company alias' => [[
            [
                'object' => 'lead',
                'field' => 'company',
                'label' => 'Primary company',
                'operators' => [['label' => 'starts with', 'value' => 'startsWith']],
            ],
            [
                'object' => 'behaviors',
                'field' => 'hit_url',
                'label' => 'Visited URL',
                'operators' => [['label' => 'contains', 'value' => 'contains']],
            ],
        ], 'lead', 'company'];
    }
}
