<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\AiEmailSectionsBundle\Integration\Config;
use MauticPlugin\AiEmailSectionsBundle\Service\ThemeCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** The editable, non-secret part of one AI runtime profile. */
final class ProfileType extends AbstractType
{
    public function __construct(private readonly ThemeCatalog $themeCatalog)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('id', HiddenType::class);
        $builder->add('name', TextType::class, [
            'label'       => 'mautic.aiemailsections.profile.name',
            'required'    => true,
            'constraints' => [new NotBlank(), new Length(max: 100)],
        ]);
        $builder->add('provider', ChoiceType::class, [
            'label'    => 'mautic.aiemailsections.config.provider',
            'choices'  => Config::providerChoices(),
            'required' => true,
        ]);
        $builder->add('base_url', UrlType::class, [
            'label'            => 'mautic.aiemailsections.config.base_url',
            'required'         => false,
            'default_protocol' => null,
        ]);
        $builder->add('model', TextType::class, [
            'label'    => 'mautic.aiemailsections.config.model',
            'required' => false,
        ]);
        $builder->add('brand', TextareaType::class, [
            'label'       => 'mautic.aiemailsections.config.brand',
            'required'    => false,
            'constraints' => [new Length(max: Config::MAX_BRAND_BRIEF_CHARS)],
        ]);
        $builder->add('tokens_enabled', YesNoButtonGroupType::class, [
            'label'    => 'mautic.aiemailsections.config.tokens_enabled',
            'required' => false,
            'data'     => array_key_exists('tokens_enabled', $options['data'] ?? [])
                ? (bool) $options['data']['tokens_enabled']
                : true,
        ]);
        $builder->add('tokens_excluded', TextareaType::class, [
            'label'    => 'mautic.aiemailsections.config.tokens_excluded',
            'required' => false,
        ]);
        $builder->add('temperature', NumberType::class, [
            'label'    => 'mautic.aiemailsections.config.temperature',
            'required' => false,
            'scale'    => 2,
        ]);
        $builder->add('max_tokens', IntegerType::class, [
            'label'    => 'mautic.aiemailsections.config.max_tokens',
            'required' => false,
        ]);
        $builder->add('placeholder_image', UrlType::class, [
            'label'    => 'mautic.aiemailsections.config.placeholder_image',
            'required' => false,
        ]);
        $builder->add('rate_limit', IntegerType::class, [
            'label'    => 'mautic.aiemailsections.config.rate_limit',
            'required' => false,
        ]);
        $builder->add('timeout', IntegerType::class, [
            'label'    => 'mautic.aiemailsections.config.timeout',
            'required' => false,
        ]);
        $builder->add('theme', ChoiceType::class, [
            'label'       => 'mautic.aiemailsections.config.theme',
            'choices'     => array_flip($this->themeCatalog->all()),
            'required'    => false,
            'placeholder' => false,
        ]);
    }

    public function buildView(FormView $view, \Symfony\Component\Form\FormInterface $form, array $options): void
    {
        $view->vars['attr']['class'] = trim(($view->vars['attr']['class'] ?? '').' ai-profile-row');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
