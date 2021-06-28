<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\FieldType;

class RecursiveLoader {
	public function loadDefinition() {
		return new SchemaDefinition(
			new LabelDefinition('Hierarchy', 'Hierarchies', null, null, '#097'), [
			'hierarchy' => new KeyDefinition(
				new StorageDefinition('hierarchy'),
				new LabelDefinition('Hierarchy', 'Hierarchies'),
				null, null, null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
					'label_singular' => new FieldDefinition(new LabelDefinition('Label Singular'), 'string', true, false),
					'label_plural' => new FieldDefinition(new LabelDefinition('Label Plural'), 'string', true, false),
					'label_description' => new FieldDefinition(new LabelDefinition('Description'), 'text', true, false),
					'label_icon' => new FieldDefinition(new LabelDefinition('Icon'), 'enum', false, false, ['values' => [], 'style' => 'compact']),
					'label_color' => new FieldDefinition(new LabelDefinition('Color'), 'string', true, false),
				], new SummaryDefinition(['%slug'])
			),
			'collection' => new KeyDefinition(
				new StorageDefinition('collection'),
				new LabelDefinition('Collection'),
				new ScopeDefinition('hierarchy'), new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
					
					'reflexive' => new FieldDefinition(new LabelDefinition('Reflexive'), 'bool', true, false),
					'singleton' => new FieldDefinition(new LabelDefinition('Singleton'), 'bool', true, false),
					'label_singular' => new FieldDefinition(new LabelDefinition('Label Singular'), 'string', true, false),
					'label_plural' => new FieldDefinition(new LabelDefinition('Label Plural'), 'string', true, false),
					'label_description' => new FieldDefinition(new LabelDefinition('Description'), 'text', true, false),
					'label_icon' => new FieldDefinition(new LabelDefinition('Icon'), 'enum', false, false,  ['values' => [], 'style' => 'compact']),
					'label_color' => new FieldDefinition(new LabelDefinition('Color'), 'string', true, false),
					'summary' => new FieldDefinition(new LabelDefinition('Summary Template'), 'string', true, false),
					'table_name' => new FieldDefinition(new LabelDefinition('Table Name'), 'string', true, false),
					'pk_name' => new FieldDefinition(new LabelDefinition('Primary Key Column Name'), 'string', true, false),
					'scope_column_name' => new FieldDefinition(new LabelDefinition('Scope Column'), 'string', true, false),
					'scope_parent_name' => new FieldDefinition(new LabelDefinition('Scope Parent Column'), 'string', true, false),
					'scope_child_name' => new FieldDefinition(new LabelDefinition('Scope Parent Column'), 'string', true, false),
					'scope_depth_name' => new FieldDefinition(new LabelDefinition('Scope Depth Column'), 'string', true, false),
					'order_column_name' => new FieldDefinition(new LabelDefinition('Order column Name'), 'string', true, false),
				], new SummaryDefinition(['%slug'])
			),
			'field' => new KeyDefinition(
				new StorageDefinition('field'),
				new LabelDefinition('Field'),
				new ScopeDefinition('collection'), null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
					'type' => new FieldDefinition(new LabelDefinition('Type'), 'enum', true, false, ['style' => 'compact', 'values' => [
						'string',
						'text',
						'file',
						'reference',
						'bool',
						'date',
						'datetime',
						'decimal',
						'enum',
						'float',
						'hash',
						'integer',
						'json',
						'time',
						'timeRange',
						'dateRange',
						'dateTimeRange',
						'integerRange',
						'floatRange',
						'decimalRange',
					]]),
					'options' => new FieldDefinition(new LabelDefinition('Options'), 'json', true, false),
					'required' => new FieldDefinition(new LabelDefinition('Required'), 'bool', true, false),
					'unique' => new FieldDefinition(new LabelDefinition('Unique'), 'bool', true, false),
					'label_singular' => new FieldDefinition(new LabelDefinition('Label Singular'), 'string', true, false),
					'label_plural' => new FieldDefinition(new LabelDefinition('Label Plural'), 'string', true, false),
					'label_description' => new FieldDefinition(new LabelDefinition('Description'), 'text', true, false),
					'label_icon' => new FieldDefinition(new LabelDefinition('Icon'), 'enum', false, false, ['style' => 'compact', 'values' => []]),
					'label_color' => new FieldDefinition(new LabelDefinition('Color'), 'string', true, false),
				], new SummaryDefinition(['%slug'])
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