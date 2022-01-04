<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


use Symfony\Component\Validator\Constraints\NotBlank;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

use App\Hierarchy\Data\MultiTreeIterator;
use App\Hierarchy\Data\MultiTree;

use RecursiveIteratorIterator;

class CreateNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];

        $fetcher = $options['storageConnection']->getFetcher();

        if($key->isScoped() || $key->isReflexive()) {
            $builder
            ->add('_nesting', Type\ChoiceType::class, [
                'label' => 'Nesting',
                'constraints' => [
                    new NotBlank(),
                ],
                'choices' => new RecursiveIteratorIterator(new MultiTreeIterator(new MultiTree([],[]), $key->getId(), null, null, 0), RecursiveIteratorIterator::SELF_FIRST),
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