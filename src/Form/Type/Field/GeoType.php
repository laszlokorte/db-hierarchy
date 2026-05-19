<?php

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

use App\Hierarchy\Schema\Field;

class GeoType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void {
		$builder->add('lat', Type\TextType::class, [
        ]);
        $builder->add('long', Type\TextType::class, [
        ]);
	}

	public function configureOptions(OptionsResolver $resolver): void
    {
    	$resolver->setDefaults([
            'compound' => true,
        ]);

        $resolver->setRequired('field', true);
        $resolver->setAllowedTypes('field', Field::class);

        $resolver->setDefault('required', function (Options $options) {
            return $options['field']->isRequired();
        });
        $resolver->setDefault('label', function (Options $options) {
            return $options['field']->getLabel()->getString();
        });
        $resolver->setDefault('help', function (Options $options) {
            return $options['field']->getLabel()->getDescription();
        });
        $resolver->setDefault('constraints', function (Options $options, $previousValue) {
            return $options['field']->isRequired() ? [
                new Assert\NotBlank(),
                ...$previousValue
            ] : $previousValue;
        });
    }

    public function getBlockPrefix() : string
    {
        return 'hierarchy_geo';
    }
}
