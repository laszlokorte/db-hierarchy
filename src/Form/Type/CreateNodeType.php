<?php

namespace App\Form\Type;

use App\Form\Type\Nesting\NestingType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;


use Symfony\Component\Validator\Constraints\NotBlank;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;


class CreateNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];

        $movementService = $options['storageConnection']->getMovementService();



        if($key->isScoped() || $key->isReflexive()) {

            if(!$key->isScoped()) {
                $label = 'Parent ' . $key->getLabel()->getSingular();
            } elseif(!$key->isReflexive() ) {
                $label = $key->getScopeKey()->getLabel()->getSingular();
            } else {
                $label = 'Parent ' . $key->getScopeKey()->getLabel()->getSingular() . '/' . $key->getLabel()->getSingular();
            }
            $builder
            ->add('_nesting', NestingType::class, [
                'label' => $label,
                'constraints' => $key->isScoped() ? [
                    new NotBlank(),
                ] : [],
                'key' => $options['key'],
                'storageConnection' => $options['storageConnection'],
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

        if($key->isSingleton()) {
            $buttons
            ->add('create', Type\SubmitType::class, [
                'label' => 'Save', 
                'attr' => ['class' => 'form-button primary']
            ])
            ->add('create_stay', Type\SubmitType::class, [
                'label' => 'Save (stay here)', 
                'attr' => ['class' => 'form-button']
            ]);

        } else {
            $buttons
            ->add('create', Type\SubmitType::class, [
                'label' => 'Create', 
                'attr' => ['class' => 'form-button primary']
            ])
            ->add('create_stay', Type\SubmitType::class, [
                'label' => 'Create (stay here)', 
                'attr' => ['class' => 'form-button']
            ]);
        }

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
    }
}