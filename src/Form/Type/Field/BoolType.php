<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

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
            'choices'  => array_combine(
                ['no', 'yes'],
                ['no', 'yes']
            ),
            'data' => 'no',
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
        return Type\ChoiceType::class;
    }

    public function getBlockPrefix()
    {
        return 'hierarchy_bool';
    }
}