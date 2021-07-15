<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

class CreateNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];

        if($key->isScoped()) {
            $builder
            ->add('_scope', TextType::class, [
                'label' => $key->getScopeKey()->getLabel()->getString(), 
            ]);
        }

        if($key->isReflexive()) {
            $builder
            ->add('_parent', TextType::class, [
                'label' => 'Parent ' . $key->getLabel()->getString(), 
            ]);
        }

        $builder->add(
            $builder->create('fields', KeyFieldsType::class, [
                'by_reference' => false, 
                'label' => false,
                'key' => $options['key'],
                'storageConnection' => $options['storageConnection'],
            ])
        );
        
        $buttons = $builder->create('buttons', ActionType::class, [
            'label' => false,
        ]);

        $buttons
            ->add('create', SubmitType::class, [
                'label' => 'Create', 
                'attr' => ['class' => 'form-button primary']
            ])
            ->add('create_stay', SubmitType::class, [
                'label' => 'Create (stay here)', 
                'attr' => ['class' => 'form-button']
            ]);

        $builder->add($buttons);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('key');
        $resolver->setAllowedTypes('key', Key::class);
        $resolver->setRequired('storageConnection');
        $resolver->setAllowedTypes('storageConnection', StorageConnection::class);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['grid'] = true;
    }
}