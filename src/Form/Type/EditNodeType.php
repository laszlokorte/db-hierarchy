<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

class EditNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
    	$key = $options['key'];

        $builder->add(
            $builder->create('field', KeyFieldsType::class, [
                'by_reference' => false, 
                'label' => false,
                'key' => $options['key'],
                'storageConnection' => $options['storageConnection'],
            ])
        );
        $builder
            ->add('update', SubmitType::class, [
                'label' => 'Update', 
                'attr' => ['class' => 'form-button primary']
            ]);
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