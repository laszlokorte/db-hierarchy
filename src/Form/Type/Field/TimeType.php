<?php

namespace App\Form\Type\Field;

use App\Hierarchy\Schema\Field;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TimeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
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
        return Type\TimeType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'hierarchy_time';
    }
}
