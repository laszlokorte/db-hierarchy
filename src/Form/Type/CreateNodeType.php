<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;


use Symfony\Component\Validator\Constraints\NotBlank;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

use App\Hierarchy\Data\MultiTreeIterator;
use App\Hierarchy\Data\MultiTree;

use RecursiveIteratorIterator;
use RecursiveTreeIterator;

class CreateNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];

        $movementService = $options['storageConnection']->getMovementService();

        if($key->isScoped() || $key->isReflexive()) {
            $builder
            ->add('_nesting', Type\ChoiceType::class, [
                'label' => 'Nesting',
                'constraints' => [
                    new NotBlank(),
                ],
                'choice_loader' => new CallbackChoiceLoader(function() use ($key, $movementService) {
                    $treeIterator = new RecursiveTreeIterator(new MultiTreeIterator($movementService->findNodeMoveTargets($key->getId(), null), $key->getScopeKey()->getId(), null, null, 0));

                    $treeIterator->setPrefixPart(RecursiveTreeIterator::PREFIX_MID_LAST, ' ');

                    $treeValueIterator = new RecursiveIteratorIterator(new MultiTreeIterator($movementService->findNodeMoveTargets($key->getId(), null), $key->getScopeKey()->getId(), null, null, 0), RecursiveIteratorIterator::SELF_FIRST
                    );

                    return array_map(function($a,$b) {
                        return (object)[
                            'disabled' => !is_object($b),
                            'value' => is_object($b) ? $b->getId() : rand(0, 1000),
                            'label' => (string) $a,
                        ];
                    }, iterator_to_array($treeIterator), iterator_to_array($treeValueIterator));
                }),
                
                'choice_label' => function($entry) { return $entry ? $entry->label : null; },
                'choice_value' => function($entry) { return $entry ? $entry->value : null; },
                'choice_attr' => function($key, $val, $index) {
                    return $key->disabled??false ? ['disabled' => 'disabled'] : [];
                },
            ]);
        }

        $builder->add(
            $builder->create('fields', KeyFieldsType::class, [
                'by_reference' => false, 
                'label' => false,
                'key' => $options['key'],
                'storageConnection' => $options['storageConnection'],
            ])
        );
        
        $buttons = $builder->create('buttons', ActionType::class, [
            'label' => false,
        ]);

        if($key->isSingleton()) {
            $buttons
            ->add('create', Type\SubmitType::class, [
                'label' => 'Save', 
                'attr' => ['class' => 'form-button primary']
            ])
            ->add('create_stay', Type\SubmitType::class, [
                'label' => 'Save (stay here)', 
                'attr' => ['class' => 'form-button']
            ]);

        } else {
            $buttons
            ->add('create', Type\SubmitType::class, [
                'label' => 'Create', 
                'attr' => ['class' => 'form-button primary']
            ])
            ->add('create_stay', Type\SubmitType::class, [
                'label' => 'Create (stay here)', 
                'attr' => ['class' => 'form-button']
            ]);
        }

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