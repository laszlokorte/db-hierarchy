<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

use Symfony\Component\Form\Extension\Core\Type;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Schema\Field;

class BoolType extends AbstractType
{
	public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'expanded' => true,
            'choices'  => array_combine(
                ['no', 'yes'],
                ['no', 'yes']
            ),
            'data' => 'no',
        ]);
    }

    public function getParent(): string
    {
        return Type\ChoiceType::class;
    }
}