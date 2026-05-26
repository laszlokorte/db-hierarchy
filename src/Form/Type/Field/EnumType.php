<?php

namespace App\Form\Type\Field;

use App\Hierarchy\Schema\Field;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EnumType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('existing');
        $resolver->setDefaults(['existing' => false]);
        $resolver->setRequired('field', true);
        $resolver->setAllowedTypes('field', Field::class);

        $resolver->setDefault('required', function (Options $options) {
            /** @var Field $field */
            $field = $options['field'];

            return $field->isRequired();
        });
        $resolver->setDefault('label', function (Options $options) {
            /** @var Field $field */
            $field = $options['field'];

            return $field->getLabel()->getString();
        });
        $resolver->setDefault('help', function (Options $options) {
            /** @var Field $field */
            $field = $options['field'];

            return $field->getLabel()->getDescription();
        });
        $resolver->setDefault('expanded', function (Options $options) {
            return 'compact' != $options['field']->getOption('style');
        });
        $resolver->setDefault('choices', function (Options $options) {
            return array_combine(
                $options['field']->getOption('values'),
                $options['field']->getOption('values')
            );
        });
        $resolver->setDefault('data', function (Options $options) {
            return current($options['field']->getOption('values'));
        });
        $resolver->setDefault('constraints', function (Options $options, $previousValue) {
            /** @var Field $field */
            $field = $options['field'];

            return $field->isRequired() ? [
                new Assert\NotBlank(),
                ...$previousValue,
            ] : $previousValue;
        });
    }

    public function getParent(): string
    {
        return Type\ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'hierarchy_enum';
    }
}
