<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\FieldType;

class Loader {
	public function loadDefinition() {
		return new SchemaDefinition(
			new LabelDefinition('Hierarchy'),
			[
			'site' => new KeyDefinition(
				'site',
				new LabelDefinition('Site'),
				null, new ReflexivityDefinition(), null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'route' => new KeyDefinition(
				'route',
				new LabelDefinition('Route'),
				new ScopeDefinition('site'), new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'menu' => new KeyDefinition(
				'menu',
				new LabelDefinition('Menu'),
				null, null, null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
					'blub' => new FieldDefinition(new LabelDefinition('Blub'), 'text', true, false),
				]
			),
		],
		[
			'text' => new FieldType\TextType(),
		]);
	}
}