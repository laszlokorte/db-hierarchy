<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IsolatedFieldType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'disabled' => true,
            'required' => false,
            'mapped' => false,
        ]);
        $resolver->setRequired('url');
        $resolver->setRequired('field');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['url'] = $options['url'];
        $view->vars['field'] = $options['field'];
    }

    public function getBlockPrefix(): string
    {
        return 'isolated';
    }
}
