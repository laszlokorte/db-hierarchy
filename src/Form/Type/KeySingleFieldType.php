<?php

namespace App\Form\Type;

use App\Form\Type\Field as HierarchyField;
use App\Form\Type\Reference\ReferenceType;
use App\Hierarchy\Schema\Key;
use App\Hierarchy\Storage\Relational\StorageConnection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class KeySingleFieldType extends AbstractType
{
    private $fieldMapping = [
        'string' => HierarchyField\StringType::class,
        'dsn' => HierarchyField\DsnType::class,
        'text' => HierarchyField\TextType::class,
        'file' => HierarchyField\FileType::class,
        'bool' => HierarchyField\BoolType::class,
        'date' => HierarchyField\DateType::class,
        'datetime' => HierarchyField\DateTimeType::class,
        'decimal' => HierarchyField\DecimalType::class,
        'enum' => HierarchyField\EnumType::class,
        'float' => HierarchyField\FloatType::class,
        'hash' => HierarchyField\HashType::class,
        'password' => HierarchyField\PasswordType::class,
        'integer' => HierarchyField\IntegerType::class,
        'json' => HierarchyField\JsonType::class,
        'time' => HierarchyField\TimeType::class,
        'email' => HierarchyField\EmailType::class,
        'color' => HierarchyField\ColorType::class,
        'geo' => HierarchyField\GeoType::class,
        'url' => HierarchyField\UrlType::class,
        'svg' => HierarchyField\SvgType::class,
        'sql' => HierarchyField\SqlType::class,
        'icon' => HierarchyField\IconType::class,
    ];

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $key = $options['key'];
        $storageConnection = $options['storageConnection'];

        $field = $key->getField($options['fieldId']);
        $type = $field->getTypeId();
        if (isset($this->fieldMapping[$type])) {
            $builder->add($field->getId(), $this->fieldMapping[$type], [
                'field' => $field,
                'nodeId' => $options['nodeId'],
            ]);
        } else {
            switch ($field->getTypeId()) {
                case 'reference':
                    $builder
                    ->add($field->getId(), ReferenceType::class, [
                        'field' => $field,
                        'storageConnection' => $storageConnection,
                        'nodeId' => null,
                    ]);
                    break;

                case 'timeRange':
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\TimeType::class)
                    ->add('end', Type\TimeType::class);
                    $builder->add($range);
                    break;
                case 'dateRange':
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\DateType::class)
                    ->add('end', Type\DateType::class);
                    $builder->add($range);
                    break;
                case 'dateTimeRange':
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\DateTimeType::class)
                    ->add('end', Type\DateTimeType::class);
                    $builder->add($range);
                    break;
                case 'integerRange':
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\NumberType::class)
                    ->add('end', Type\NumberType::class);
                    $builder->add($range);
                    break;
                case 'floatRange':
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\NumberType::class)
                    ->add('end', Type\NumberType::class);
                    $builder->add($range);
                    break;
                case 'decimalRange':
                    $range = $builder->create($field->getId(), FormType::class, [
                        'label' => $field->getLabel()->getString(),
                        'required' => $field->isRequired(),
                    ]);
                    $range
                    ->add('start', Type\NumberType::class)
                    ->add('end', Type\NumberType::class);
                    $builder->add($range);
                    break;
                default:
                    throw new \Exception('unknown field type '.$field->getTypeId());
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('hierarchySlug');
        $resolver->setRequired('nodeId');
        $resolver->setRequired('fieldId');
        $resolver->setDefaults(['nodeId' => null]);
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
