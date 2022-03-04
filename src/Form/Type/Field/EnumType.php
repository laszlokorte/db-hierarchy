<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Hierarchy\Schema\Field;

class EnumType extends AbstractType
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
        $resolver->setDefault('expanded', function (Options $options) {
            return $options['field']->getOption('style') != 'compact';
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
    }

    public function getParent(): string
    {
        return Type\ChoiceType::class;
    }

    public function getBlockPrefix()
    {
        return 'hierarchy_enum';
    }
}