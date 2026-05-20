<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\FieldType;

class Loader
{
    public function loadDefinition()
    {
        return new SchemaDefinition(
            new LabelDefinition('Hierarchy', 'Hierarchies', null, null, '#058591'), [
                'site' => new KeyDefinition(
                    new StorageDefinition('site'),
                    new LabelDefinition('Site'),
                    null, new ReflexivityDefinition(), null, [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'essen' => new KeyDefinition(
                    new StorageDefinition('essen'),
                    new LabelDefinition('Essen', 'Essen'),
                    null, null, null, [
                        'name' => new FieldDefinition(new LabelDefinition('Name'), 'string', true, false),
                        'bewertung' => new FieldDefinition(new LabelDefinition('Bewertung'), 'string', true, false),
                    ], new SummaryDefinition(['%name'])
                ),

                'zutaten' => new KeyDefinition(
                    new StorageDefinition('zutaten'),
                    new LabelDefinition('Zutat', 'Zutaten'),
                    new ScopeDefinition('essen', null), null, null, [
                        'name' => new FieldDefinition(new LabelDefinition('Name'), 'string', true, false),
                    ], new SummaryDefinition(['%name'])
                ),
                'site_generator' => new KeyDefinition(
                    new StorageDefinition('site_generator'),
                    new LabelDefinition('Site Generator'),
                    new ScopeDefinition('site', null, true), new ReflexivityDefinition(), null, [
                        'query' => new FieldDefinition(new LabelDefinition('Query'), 'string', true, false),
                    ], new SummaryDefinition([])
                ),
                'route' => new KeyDefinition(
                    new StorageDefinition('route'),
                    new LabelDefinition('Route'),
                    new ScopeDefinition('site'), new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'route_generator' => new KeyDefinition(
                    new StorageDefinition('route_generator'),
                    new LabelDefinition('Route Generator'),
                    new ScopeDefinition('route', null, true), null, null, [
                        'query' => new FieldDefinition(new LabelDefinition('Query'), 'string', true, false),
                    ], new SummaryDefinition([])
                ),
                'content' => new KeyDefinition(
                    new StorageDefinition('content'),
                    new LabelDefinition('Content'),
                    new ScopeDefinition('route'), new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'content_generator' => new KeyDefinition(
                    new StorageDefinition('content_generator'),
                    new LabelDefinition('Content Generator'),
                    new ScopeDefinition('content', null, true), null, null, [
                        'query' => new FieldDefinition(new LabelDefinition('Query'), 'string', true, false),
                    ], new SummaryDefinition([])
                ),
                'menu' => new KeyDefinition(
                    new StorageDefinition('menu'),
                    new LabelDefinition('Menu'),
                    null, null, null, [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'menu_item' => new KeyDefinition(
                    new StorageDefinition('menu_item'),
                    new LabelDefinition('Menu Item'),
                    new ScopeDefinition('menu'), new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'resource_directory' => new KeyDefinition(
                    new StorageDefinition('resource_directory'),
                    new LabelDefinition('Directory', 'Directories'),
                    null, new ReflexivityDefinition(), null, [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'resource' => new KeyDefinition(
                    new StorageDefinition('resource'),
                    new LabelDefinition('Resource'),
                    new ScopeDefinition('resource_directory'), null, null, [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'example_parent' => new KeyDefinition(
                    new StorageDefinition('example_parent'),
                    new LabelDefinition('Exmp Parent'),
                    null, null, null, [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'example_child' => new KeyDefinition(
                    new StorageDefinition('example_child'),
                    new LabelDefinition('Exmp Child'),
                    new ScopeDefinition('example_parent'), null, null, [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'sorted_parent' => new KeyDefinition(
                    new StorageDefinition('sorted_parent'),
                    new LabelDefinition('Sorted Parent'),
                    null, null, new OrderDefinition('priority', 'DESC'), [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'sorted_child' => new KeyDefinition(
                    new StorageDefinition('sorted_child'),
                    new LabelDefinition('Sorted Child'),
                    new ScopeDefinition('sorted_parent'), null, new OrderDefinition('priority', 'DESC'), [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'sorted_tree' => new KeyDefinition(
                    new StorageDefinition('sorted_tree'),
                    new LabelDefinition('Sorted Tree'),
                    null, new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'upload' => new KeyDefinition(
                    new StorageDefinition('upload'),
                    new LabelDefinition('Upload'),
                    null, null, null, [
                        'file' => new FieldDefinition(new LabelDefinition('File'), 'file', false, false),
                    ], new SummaryDefinition(['%file'])
                ),
                'grid' => new KeyDefinition(
                    new StorageDefinition('grid'),
                    new LabelDefinition('Grid'),
                    null, null, null, [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'grid_column' => new KeyDefinition(
                    new StorageDefinition('grid_column'),
                    new LabelDefinition('Grid Column'),
                    new ScopeDefinition('grid'), null, new OrderDefinition('priority', 'DESC'), [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'grid_row' => new KeyDefinition(
                    new StorageDefinition('grid_row'),
                    new LabelDefinition('Grid Row'),
                    new ScopeDefinition('grid'), null, new OrderDefinition('priority', 'DESC'), [
                        'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
                    ], new SummaryDefinition(['%slug'])
                ),
                'link' => new KeyDefinition(
                    new StorageDefinition('link'),
                    new LabelDefinition('Link'),
                    null, null, null, [
                        'site' => new FieldDefinition(new LabelDefinition('Site'), 'reference', true, false, ['target' => 'site']),
                        'essen' => new FieldDefinition(new LabelDefinition('Essen'), 'reference', true, false, ['target' => 'essen', 'style' => 'expanded']),
                        'public' => new FieldDefinition(new LabelDefinition('Public'), 'bool', true, false),
                        'description' => new FieldDefinition(new LabelDefinition('Description'), 'text', true, false),

                        'exampleBoolA' => new FieldDefinition(new LabelDefinition('eBoolA'), 'bool', true, false, ['explicit' => true]),
                        'exampleBoolB' => new FieldDefinition(new LabelDefinition('eBoolB'), 'bool', true, false, ['style' => 'compact', 'explicit' => true]),
                        'exampleBoolC' => new FieldDefinition(new LabelDefinition('eBoolC'), 'bool', true, false, ['style' => 'check', 'explicit' => false]),
                        'exampleDate' => new FieldDefinition(new LabelDefinition('eDate'), 'date', true, false),
                        'exampleDateTime' => new FieldDefinition(new LabelDefinition('eDateTime'), 'datetime', true, false),
                        'exampleDecimal' => new FieldDefinition(new LabelDefinition('eDecimal'), 'decimal', true, false),
                        'exampleEnum' => new FieldDefinition(new LabelDefinition('eEnum'), 'enum', true, false, ['explicit' => true, 'values' => ['Foo', 'Bar', 'Baz']]),
                        'exampleEnumB' => new FieldDefinition(new LabelDefinition('eEnumB'), 'enum', true, false, ['style' => 'compact', 'explicit' => true, 'values' => ['Foo', 'Bar', 'Baz']]),
                        'exampleFloat' => new FieldDefinition(new LabelDefinition('eFloat'), 'float', true, false),
                        'exampleHash' => new FieldDefinition(new LabelDefinition('eHash'), 'hash', true, false),
                        'exampleInteger' => new FieldDefinition(new LabelDefinition('eInteger'), 'integer', true, false),
                        'exampleJson' => new FieldDefinition(new LabelDefinition('eJson'), 'json', true, false),
                        'exampleTime' => new FieldDefinition(new LabelDefinition('eTime'), 'time', true, false),
                    ], new SummaryDefinition([])
                ),
            ],
            [
                'string' => new FieldType\StringType(),
                'text' => new FieldType\TextType(),
                'file' => new FieldType\FileType(),
                'reference' => new FieldType\ReferenceType(),

                'bool' => new FieldType\BooleanType(),
                'date' => new FieldType\DateType(),
                'datetime' => new FieldType\DateTimeType(),
                'decimal' => new FieldType\DecimalType(),
                'enum' => new FieldType\EnumType(),
                'float' => new FieldType\FloatType(),
                'hash' => new FieldType\HashType(),
                'integer' => new FieldType\IntegerType(),
                'json' => new FieldType\JsonType(),
                'time' => new FieldType\TimeType(),

                'timeRange' => new FieldType\RangeType(new FieldType\TimeType()),
                'dateRange' => new FieldType\RangeType(new FieldType\DateType()),
                'dateTimeRange' => new FieldType\RangeType(new FieldType\DateTimeType()),
                'integerRange' => new FieldType\RangeType(new FieldType\IntegerType()),
                'floatRange' => new FieldType\RangeType(new FieldType\FloatType()),
                'decimalRange' => new FieldType\RangeType(new FieldType\DecimalType()),
            ]);
    }
}
