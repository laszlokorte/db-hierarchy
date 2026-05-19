<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

use App\Hierarchy\Schema\Field;

class FloatType extends AbstractType
{
	public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('field', true);
        $resolver->setAllowedTypes('field', Field::class);

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
        return Type\NumberType::class;
    }

    public function getBlockPrefix() : string
    {
        return 'hierarchy_float';
    }
}
