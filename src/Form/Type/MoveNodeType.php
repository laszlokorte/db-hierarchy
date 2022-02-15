<?php

namespace App\Form\Type;

use App\Form\Type\Nesting\NestingType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

class MoveNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];
        $storageConnection = $options['storageConnection'];
        $nodeId = $options['nodeId'];

        $builder->add('target', NestingType::class, [
            'label' => 'Move To',
            'key' => $key,
            'storageConnection' => $storageConnection,
            'nodeId' => $nodeId,
        ]);

        $buttons = $builder->create('buttons', ActionType::class);

        $buttons
            ->add('move', Type\SubmitType::class, [
                'label' => 'Move', 
                'attr' => ['class' => 'form-button primary']
            ]);

        $builder->add($buttons);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('key');
        $resolver->setAllowedTypes('key', Key::class);
        $resolver->setRequired('storageConnection');
        $resolver->setAllowedTypes('storageConnection', StorageConnection::class);
        $resolver->setRequired('nodeId');
        $resolver->setAllowedTypes('nodeId', 'string');

        $resolver->setDefaults([
            'csrf_token_id'   => 'hierarchy_move',
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
    }
    
}