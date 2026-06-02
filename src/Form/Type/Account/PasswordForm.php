<?php

namespace App\Form\Type\Account;

use App\Form\Type\ActionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fields = $builder->create('fields', ActionType::class);
        $fields->add(
            'password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'New Password', 'attr' => ['autocomplete' => 'new-password'], 'always_empty' => false],
                'second_options' => ['label' => 'Confirm Password', 'attr' => ['autocomplete' => 'off'], 'always_empty' => false],
                'constraints' => [
                    new NotBlank(),
                ],
            ]);

        $buttons = $builder->create('buttons', ActionType::class);
        $buttons->add(
            'submit', SubmitType::class, [
                'label' => 'Save',
                'attr' => ['class' => 'form-button primary'],
            ]);
        $builder->add($fields);
        $builder->add($buttons);
    }
}
