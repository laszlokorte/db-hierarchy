<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Hierarchy\Schema\Field;

class SqlType extends AbstractType
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
        $resolver->setDefault('attr', function (Options $options) {
            return ['class' => 'form-field multiline monospace'];
        });
    }

    public function getParent(): string
    {
        return Type\TextareaType::class;
    }

    public function getBlockPrefix()
    {
        return 'hierarchy_sql';
    }
}