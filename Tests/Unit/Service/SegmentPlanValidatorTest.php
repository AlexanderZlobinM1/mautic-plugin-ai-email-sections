<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Tests\Unit\Service;

use MauticPlugin\AiEmailSectionsBundle\Exception\SegmentPlanException;
use MauticPlugin\AiEmailSectionsBundle\Service\SegmentPlanValidator;
use PHPUnit\Framework\TestCase;

final class SegmentPlanValidatorTest extends TestCase
{
    private const CATALOG = [
        [
            'object'    => 'behaviors',
            'field'     => 'hit_url',
            'label'     => 'Visited URL',
            'type'      => 'text',
            'operators' => [
                ['label' => 'contains', 'value' => 'like'],
                ['label' => 'equals', 'value' => '='],
            ],
        ],
        [
            'object'    => 'lead',
            'field'     => 'device_os',
            'label'     => 'Device OS',
            'type'      => 'device_os',
            'operators' => [['label' => 'including', 'value' => 'in']],
        ],
    ];

    public function testAcceptsOnlyLiveFieldsAndOperators(): void
    {
        $result = (new SegmentPlanValidator())->validate([
            'groups' => [[
                'conditions' => [
                    ['object' => 'behaviors', 'field' => 'hit_url', 'operator' => 'like', 'value' => '/shop'],
                    ['object' => 'lead', 'field' => 'device_os', 'operator' => 'in', 'value' => ['Android']],
                ],
            ]],
            'summary' => 'Visitors using Android.',
        ], self::CATALOG);

        $this->assertCount(2, $result['groups'][0]['conditions']);
        $this->assertSame('/shop', $result['groups'][0]['conditions'][0]['value']);
    }

    public function testRejectsInventedField(): void
    {
        $this->expectException(SegmentPlanException::class);
        $this->expectExceptionMessage('The generated field lead.invented is not offered by this Mautic.');

        (new SegmentPlanValidator())->validate([
            'groups' => [[
                'conditions' => [[
                    'object' => 'lead', 'field' => 'invented', 'operator' => '=', 'value' => 'x',
                ]],
            ]],
        ], self::CATALOG);
    }

    public function testRejectsOperatorNotOfferedForField(): void
    {
        $this->expectException(SegmentPlanException::class);
        $this->expectExceptionMessage('The generated operator regexp is not offered for lead.device_os.');

        (new SegmentPlanValidator())->validate([
            'groups' => [[
                'conditions' => [[
                    'object' => 'lead', 'field' => 'device_os', 'operator' => 'regexp', 'value' => 'Android',
                ]],
            ]],
        ], self::CATALOG);
    }

    public function testNormalizesCommonComparisonSymbolsWhenTheFieldOffersMauticOperator(): void
    {
        $catalog = [[
            'object' => 'behaviors', 'field' => 'hit_url_date',
            'operators' => [['label' => 'greater than or equal', 'value' => 'gte']],
        ]];
        $result = (new SegmentPlanValidator())->validate([
            'groups' => [['conditions' => [[
                'object' => 'behaviors', 'field' => 'hit_url_date', 'operator' => '>=', 'value' => '-10 days',
            ]]]],
        ], $catalog);

        $this->assertSame('gte', $result['groups'][0]['conditions'][0]['operator']);
    }

    public function testNormalizesDisplayOperatorLabelsForCompanyAndPageHitConditions(): void
    {
        $catalog = [
            [
                'object' => 'company',
                'field' => 'companyname',
                'operators' => [['label' => 'starts with', 'value' => 'startsWith']],
            ],
            [
                'object' => 'behaviors',
                'field' => 'hit_url',
                'operators' => [['label' => 'contains', 'value' => 'contains']],
            ],
        ];

        $result = (new SegmentPlanValidator())->validate([
            'groups' => [
                ['conditions' => [
                    ['object' => 'company', 'field' => 'companyname', 'operator' => 'starts with', 'value' => 'O'],
                    ['object' => 'behaviors', 'field' => 'hit_url', 'operator' => 'contains', 'value' => 'apple.com'],
                ]],
                ['conditions' => [
                    ['object' => 'company', 'field' => 'companyname', 'operator' => 'starts_with', 'value' => 'B'],
                    ['object' => 'behaviors', 'field' => 'hit_url', 'operator' => 'contains', 'value' => 'apple.com'],
                ]],
            ],
        ], $catalog);

        $this->assertSame('startsWith', $result['groups'][0]['conditions'][0]['operator']);
        $this->assertSame('startsWith', $result['groups'][1]['conditions'][0]['operator']);
        $this->assertSame('contains', $result['groups'][0]['conditions'][1]['operator']);
    }
}
