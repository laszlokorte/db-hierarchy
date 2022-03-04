<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Hierarchy\Schema\Field;

class DateTimeType extends AbstractType
{
	public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('field', true);
        $resolver->setAllowedTypes('field', Field::class);

        $resolver->setDefaults([
	        'date_label' => false,
	        'time_label' => false,
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
    }

    public function getParent(): string
    {
        return Type\DateTimeType::class;
    }

    public function getBlockPrefix()
    {
        return 'hierarchy_date_time';
    }
}