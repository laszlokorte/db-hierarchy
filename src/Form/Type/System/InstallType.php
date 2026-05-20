<?php

namespace App\Form\Type\System;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class InstallType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('only_views', HiddenType::class);
        $builder->add('submit', SubmitType::class, [
            'label' => 'Reinstall',
        ]);
    }
}
