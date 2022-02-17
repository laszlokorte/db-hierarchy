<?php

namespace App\Form\Type;

use App\Form\Type\Reference\ReferenceType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Validator\Constraints as Assert;

use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Key;

use App\Form\Type\Field as HierarchyField;

class KeyFieldsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];
        $storageConnection = $options['storageConnection'];
        
        foreach ($key->getFields() as $field) {
            switch($field->getType()) {
                case "string":
                    $builder
                    ->add($field->getId(), Type\TextType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                        'help' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                        'constraints' => $field->isRequired() ? [new Assert\NotBlank()] : [],
                    ]);
                    break;
                case "text":
                    $builder
                    ->add($field->getId(), Type\TextareaType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "file":
                    $builder
                    ->add($field->getId(), Type\FileType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "reference":
                    $builder
                    ->add($field->getId(), ReferenceType::class, [
                        'storageConnection' => $storageConnection,
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                        'key' => $key->getReferencingKey($field->getOption('target')),
                        'nodeId' => null,
                        'constraints' => $field->isRequired() ? [new Assert\NotBlank()] : [],
                    ]);
                    break;
                case "bool":
                    $builder
                    ->add($field->getId(), HierarchyField\BoolType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "date":
                    $builder
                    ->add($field->getId(), Type\DateType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "datetime":
                    $builder
                    ->add($field->getId(), Type\DateTimeType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                        'date_label' => 'false',
                        'time_label' => 'false',
                    ]);
                    break;
                case "decimal":
                    $builder
                    ->add($field->getId(), Type\NumberType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "enum":
                    $builder
                    ->add($field->getId(), Type\ChoiceType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                        'expanded' => $field->getOption('style') != 'compact',
                        'choices'  => array_combine(
                            $field->getOption('values'),
                            $field->getOption('values')
                        ),
                        'data' => current($field->getOption('values'))
                    ]);
                    break;
                case "float":
                    $builder
                    ->add($field->getId(), Type\NumberType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "hash":
                    $builder
                    ->add($field->getId(), Type\PasswordType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "integer":
                    $builder
                    ->add($field->getId(), Type\NumberType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "json":
                    $builder
                    ->add($field->getId(), Type\TextareaType::class, [
                        'label' => $field->getLabel()->getString(),  
                        'required' => $field->isRequired(),
                        'attr' => ['class' => 'form-field multiline monospace']
                    ]);
                    break;
                case "time":
                    $builder
                    ->add($field->getId(), Type\TimeType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "email":
                    $builder
                    ->add($field->getId(), Type\EmailType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "color":
                    $builder
                    ->add($field->getId(), Type\ColorType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "geo":
                    $geo = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $geo
                    ->add('lat', Type\NumberType::class)
                    ->add('lon', Type\NumberType::class);
                    $builder->add($geo);
                    break;
                case "url":
                    $builder
                    ->add($field->getId(), Type\UrlType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                    ]);
                    break;
                case "svg":
                    $builder
                    ->add($field->getId(), Type\TextareaType::class, [
                        'label' => $field->getLabel()->getString(),  
                        'required' => $field->isRequired(),
                        'attr' => ['class' => 'form-field multiline monospace']
                    ]);
                    break;
                case "sql":
                    $builder
                    ->add($field->getId(), Type\TextareaType::class, [
                        'label' => $field->getLabel()->getString(),  
                        'required' => $field->isRequired(),
                        'attr' => ['class' => 'form-field multiline monospace']
                    ]);
                    break;
                case "icon":
                    $builder
                    ->add($field->getId(), Type\ChoiceType::class, [
                        'label' => $field->getLabel()->getString(), 
                        'required' => $field->isRequired(),
                        'choices'  => array_combine(
                            $field->getOption('values'),
                            $field->getOption('values')
                        ),
                    ]);
                    break;
                case "timeRange":
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\TimeType::class)
                    ->add('end', Type\TimeType::class);
                    $builder->add($range);
                    break;
                case "dateRange":
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\DateType::class)
                    ->add('end', Type\DateType::class);
                    $builder->add($range);
                    break;
                case "dateTimeRange":
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\DateTimeType::class)
                    ->add('end', Type\DateTimeType::class);
                    $builder->add($range);
                    break;
                case "integerRange":
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\NumberType::class)
                    ->add('end', Type\NumberType::class);
                    $builder->add($range);
                    break;
                case "floatRange":
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\NumberType::class)
                    ->add('end', Type\NumberType::class);
                    $builder->add($range);
                    break;
                case "decimalRange":
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\NumberType::class)
                    ->add('end', Type\NumberType::class);
                    $builder->add($range);
                    break;

            }
        }
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
        $view->vars['grid'] = true;
    }
}