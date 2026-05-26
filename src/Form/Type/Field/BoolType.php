<?php

namespace App\Form\Type\Field;

use App\Hierarchy\Schema\Field;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BoolType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('existing');
        $resolver->setDefaults(['existing' => false]);
        $resolver->setRequired('field', true);
        $resolver->setAllowedTypes('field', Field::class);
        $resolver->setDefaults([
            'expanded' => true,
            'choices' => [
                'no' => false,
                'yes' => true,
            ],
            'choice_label' => function ($choice, $key, $value) {
                return $choice ? 'Yes' : 'No';
            },
            'choice_value' => function ($choice) {
                return $choice ? 'yes' : 'no';
            },
        ]);

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
                new Assert\NotNull(),
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
        return 'hierarchy_bool';
    }
}
