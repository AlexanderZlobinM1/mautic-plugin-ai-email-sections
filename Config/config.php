<?php

declare(strict_types=1);

return [
    'name'        => 'Sales Snap AI Assistant',
    'description' => 'Generates email sections and prepares new segment filters from natural-language instructions.',
    'version'     => '2.0.2',
    'author'      => 'Alexander Zlobin / Sales Snap',

    'routes' => [
        'main' => [
            'ai_email_sections_generate' => [
                'path'       => '/ai-email-sections/generate',
                'controller' => 'MauticPlugin\AiEmailSectionsBundle\Controller\GenerateController::generateAction',
                'methods'    => ['POST'],
            ],
            'ai_email_sections_segment_plan' => [
                'path'       => '/ai-email-sections/segment-plan',
                'controller' => 'MauticPlugin\AiEmailSectionsBundle\Controller\GenerateController::segmentPlanAction',
                'methods'    => ['POST'],
            ],
        ],
        'public' => [],
        'api'    => [],
    ],

    'menu' => [],
];
