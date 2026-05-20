<?php

namespace App\Form\Type;

use App\Form\Type\Order\PositionType;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Storage\Relational\StorageConnection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];
        $storageConnection = $options['storageConnection'];
        $nodeId = $options['nodeId'];

        $builder->add('new_position', PositionType::class, [
            'label' => 'New Position',
            'key' => $key,
            'storageConnection' => $storageConnection,
            'nodeId' => $nodeId,
        ]);

        $buttons = $builder->create('buttons', ActionType::class);

        $buttons
            ->add('move', Type\SubmitType::class, [
                'label' => 'Reorder',
                'attr' => ['class' => 'form-button primary'],
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
            'csrf_token_id' => 'hierarchy_order',
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
    }
}
