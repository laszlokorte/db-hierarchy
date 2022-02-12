<?php

namespace App\Form\Type\Nesting;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

use App\Hierarchy\Data\MultiTreeIterator;
use App\Hierarchy\Data\MultiTree;

use RecursiveIteratorIterator;
use RecursiveTreeIterator;

class NestingType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	$choiceList = $this->createChoiceList($options);
    	$builder->setAttribute('choice_list', $choiceList);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $choiceList = $form->getConfig()->getAttribute('choice_list');

        $view->vars = array_replace($view->vars, [
            'choices' => $choiceList,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {   
        $resolver->setDefaults([
            'compound' => false,
        ]);

        $resolver->setRequired('key');
        $resolver->setAllowedTypes('key', Key::class);
        $resolver->setDefault('nodeId', null);
        $resolver->setAllowedTypes('nodeId', ['string', 'null']);
        $resolver->setRequired('storageConnection');
        $resolver->setAllowedTypes('storageConnection', StorageConnection::class);
    }

    private function createChoiceList($options) {
        $key = $options['key'];
        $movementService = $options['storageConnection']->getMovementService();


        $tree = $movementService->findNodeMoveTargets($key->getId(), null);

    	$multiTreeIterator = new MultiTreeIterator($tree, $key->isScoped() ? $key->getScopeKey() : $key, null, null, 0);

    	$treeIterator = new RecursiveTreeIterator($multiTreeIterator);

	    $treeIterator->setPrefixPart(RecursiveTreeIterator::PREFIX_MID_LAST, ' ');

	    $treeValueIterator = new RecursiveIteratorIterator($multiTreeIterator, RecursiveIteratorIterator::SELF_FIRST
	    );

	    return $treeValueIterator;
    }

    public function getBlockPrefix()
    {
        return 'hierarchy_nesting';
    }
}