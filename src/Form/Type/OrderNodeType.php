<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

class OrderNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];
        $storageConnection = $options['storageConnection'];
        $nodeId = $options['nodeId'];

        $builder->add('new_position', Type\ChoiceType::class, [
            'label' => 'New Position',
            'choice_loader' => new CallbackChoiceLoader(function() use ($key, $storageConnection, $nodeId) {
                $orderTargets = $storageConnection->getOrderingService()->findNodeSiblings($key->getId(), $nodeId);

                $choices = [];
                $down = false;
                $prevId = 'Top';

                foreach ($orderTargets->getIds() as $i => $id) {
                    if($nodeId === $id) {
                        $down = true;
                        $choices[] = (object)['label' => $prevId, 'value' => $prevId, 'disabled' => true];
                        $choices[] = (object)['label' => '(Current Position)', 'value' => $orderTargets->getOrder($id)];
                    } elseif($down) {
                        $choices[] = (object)['label' => $id, 'value' => $id.'_', 'disabled' => true];
                        $choices[] = (object)['label' => 'pute here', 'value' => $orderTargets->getOrder($id)];
                    } else {
                        $choices[] = (object)['label' => $prevId, 'value' => '_'.$id, 'disabled' => true];
                        $choices[] = (object)['label' => 'pute here', 'value' => $orderTargets->getOrder($id)];
                    }
                    $prevId = $id;
                }
                
                $choices[] = (object)['label' => 'Bottom', 'value' => 'bottom', 'disabled' => true];

                return $choices;
            }),

            'choice_label' => function($entry) { return $entry ? $entry->label : null; },
            'choice_value' => function($entry) { return $entry ? $entry->value : null; },
            'choice_attr' => function($key, $val, $index) {
                return $key->disabled??false ? ['disabled' => 'disabled'] : [];
            },
        ]);

        $buttons = $builder->create('buttons', ActionType::class);

        $buttons
            ->add('move', Type\SubmitType::class, [
                'label' => 'Reorder', 
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
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
    }
    
}