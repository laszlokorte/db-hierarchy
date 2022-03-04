<?php

namespace App\Form\Type\Reference;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Validator\Constraints as Assert;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Schema\Field;

use App\Hierarchy\Data\NodeCollectionIterator;

class ReferenceType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $field = $options['field'];
        $storageConnection = $options['storageConnection'];
        $nodeId = $options['nodeId'];

        $builder->setAttribute('choice_list', new NodeCollectionIterator($storageConnection->getQueryService()->findAllNodes($field->getOption('target'))));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
        ]);

        $resolver->setRequired('field', true);
        $resolver->setAllowedTypes('field', Field::class);
        $resolver->setRequired('storageConnection');
        $resolver->setAllowedTypes('storageConnection', StorageConnection::class);
        $resolver->setRequired('nodeId');
        $resolver->setAllowedTypes('nodeId', ['string','null']);

        $resolver->setDefault('required', function (Options $options) {
            return $options['field']->isRequired();
        });
        $resolver->setDefault('label', function (Options $options) {
            return $options['field']->getLabel()->getString();
        });
        $resolver->setDefault('help', function (Options $options) {
            return $options['field']->getLabel()->getDescription();
        });
        $resolver->setDefault('constraints', function (Options $options) {
            return $options['field']->isRequired() ? [new Assert\NotBlank()] : [];
        });
        $resolver->setDefault('constraints', function (Options $options) {
            return $options['field']->isRequired() ? [new Assert\NotBlank()] : [];
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
    	$choiceList = $form->getConfig()->getAttribute('choice_list');
        $field = $options['field'];

        $view->vars = array_replace($view->vars, [
            'choices' => $choiceList,
            'key' => $field->getKey()->getReferencingKey($field->getOption('target')),
        ]);
    }

    public function getBlockPrefix()
    {
        return 'hierarchy_reference';
    }
}