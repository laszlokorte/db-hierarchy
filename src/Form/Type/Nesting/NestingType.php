<?php

namespace App\Form\Type\Nesting;

use App\Hierarchy\Data\MultiTreeOuterIterator;
use App\Hierarchy\Data\NodeNesting;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Storage\Relational\StorageConnection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NestingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choiceList = $this->createChoiceList($options);
        $builder->setAttribute('choice_list', $choiceList);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $choiceList = $form->getConfig()->getAttribute('choice_list');

        $view->vars = array_replace($view->vars, [
            'choices' => $choiceList,
            'key' => $options['key'],
            'nodeNesting' => $options['nodeNesting'],
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
        $resolver->setDefault('nodeNesting', null);
        $resolver->setAllowedTypes('nodeNesting', [NodeNesting::class, 'null']);
        $resolver->setRequired('storageConnection');
        $resolver->setAllowedTypes('storageConnection', StorageConnection::class);
    }

    private function createChoiceList($options): array
    {
        $key = $options['key'];
        $nodeId = $options['nodeId'];
        $movementService = $options['storageConnection']->getMovementService();

        $tree = $movementService->findNodeMoveTargets($key->getId(), $nodeId);
        $multiTreeIterator = new MultiTreeOuterIterator($tree, $key->isScoped() ? $key->getScopeKey() : $key, 0);

        $treeValueIterator = new \RecursiveIteratorIterator($multiTreeIterator, \RecursiveIteratorIterator::SELF_FIRST
        );

        $result = [];

        foreach ($treeValueIterator as $node) {
            if (is_string($node) || null === $node) {
                continue;
            }

            $result[] = (object) [
                'depth' => $treeValueIterator->getDepth(),
                'node' => $node,
                'key' => $key->getId() === $node->getKey() ? $key : $key->getScopeKey(),
            ];
        }

        return $result;
    }

    public function getBlockPrefix(): string
    {
        return 'hierarchy_nesting';
    }
}
