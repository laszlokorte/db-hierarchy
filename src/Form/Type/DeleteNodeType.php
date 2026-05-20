<?php

namespace App\Form\Type;

use App\Hierarchy\Schema\Key;
use App\Hierarchy\Storage\Relational\StorageConnection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DeleteNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $buttons = $builder->create('buttons', ActionType::class);

        $buttons
            ->add('delete', SubmitType::class, [
                'label' => 'Yes, Delete!',
                'attr' => ['class' => 'action-button danger'],
            ]);

        $builder
            ->add('cascade', HiddenType::class, [
                'empty_data' => 'no',
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
    }
}
