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

use App\Hierarchy\Data\MultiTreeOuterIterator;
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
            'key' => $options['key'],
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
    	$multiTreeIterator = new MultiTreeOuterIterator($tree, $key->isScoped() ? $key->getScopeKey() : $key, 0);

	    $treeValueIterator = new RecursiveIteratorIterator($multiTreeIterator, RecursiveIteratorIterator::SELF_FIRST
	    );

	    return array_filter(iterator_to_array($treeValueIterator), fn($x) => !is_string($x) && $x !== null);
    }

    public function getBlockPrefix()
    {
        return 'hierarchy_nesting';
    }
}