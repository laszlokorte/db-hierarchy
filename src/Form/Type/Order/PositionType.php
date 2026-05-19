<?php

namespace App\Form\Type\Order;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

use App\Hierarchy\Data\NodeCollectionIterator;

class PositionType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];
        $storageConnection = $options['storageConnection'];
        $nodeId = $options['nodeId'];

        $builder->setAttribute('choice_list', new NodeCollectionIterator($storageConnection->getOrderingService()->findNodeSiblings($key->getId(), $nodeId)));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
        ]);

        $resolver->setRequired('key');
        $resolver->setAllowedTypes('key', Key::class);
        $resolver->setRequired('storageConnection');
        $resolver->setAllowedTypes('storageConnection', StorageConnection::class);
        $resolver->setRequired('nodeId');
        $resolver->setAllowedTypes('nodeId', 'string');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
    	$choiceList = $form->getConfig()->getAttribute('choice_list');

        $view->vars = array_replace($view->vars, [
            'choices' => $choiceList,
            'key' => $options['key'],
        ]);
    }

    public function getBlockPrefix() : string
    {
        return 'hierarchy_position';
    }
}
