<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Validator\Constraints as Assert;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Schema\Field;

class BoolType extends AbstractType
{
	public function configureOptions(OptionsResolver $resolver): void
    {
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
            return $options['field']->isRequired();
        });
        $resolver->setDefault('label', function (Options $options) {
            return $options['field']->getLabel()->getString();
        });
        $resolver->setDefault('help', function (Options $options) {
            return $options['field']->getLabel()->getDescription();
        });
        $resolver->setDefault('constraints', function (Options $options, $previousValue) {
            return $options['field']->isRequired() ? [
                new Assert\NotBlank(),
                ...$previousValue
            ] : $previousValue;
        });
    }

    public function getParent(): string
    {
        return Type\ChoiceType::class;
    }

    public function getBlockPrefix()
    {
        return 'hierarchy_bool';
    }
}