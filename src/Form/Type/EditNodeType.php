<?php

namespace App\Form\Type;

use App\Hierarchy\Schema\Key;
use App\Hierarchy\Storage\Relational\StorageConnection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EditNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];

        $builder->add(
            $builder->create('fields', KeyFieldsType::class, [
                'nodeId' => $options['nodeId'],
                'hierarchySlug' => $options['hierarchySlug'],
                'by_reference' => false,
                'label' => false,
                'key' => $options['key'],
                'nodeId' => $options['nodeId'],
                'storageConnection' => $options['storageConnection'],
            ])
        );

        $buttons = $builder->create('buttons', ActionType::class, [
            'label' => false,
        ]);

        $buttons
            ->add('update', Type\SubmitType::class, [
                'label' => 'Update',
                'attr' => ['class' => 'form-button primary'],
            ])
            ->add('update_stay', Type\SubmitType::class, [
                'label' => 'Update (stay here)',
                'attr' => ['class' => 'form-button'],
            ]);

        $builder->add($buttons);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('hierarchySlug');
        $resolver->setRequired('key');
        $resolver->setAllowedTypes('key', Key::class);
        $resolver->setRequired('nodeId');
        $resolver->setRequired('storageConnection');
        $resolver->setAllowedTypes('storageConnection', StorageConnection::class);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
    }
}
