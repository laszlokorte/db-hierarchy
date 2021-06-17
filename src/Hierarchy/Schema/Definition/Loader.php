<?php

namespace App\Hierarchy\Schema\Definition;

use App\Hierarchy\Schema\FieldType;

class Loader {
	public function loadDefinition() {
		return new SchemaDefinition(
			new LabelDefinition('Hierarchy'),
			[
			'site' => new KeyDefinition(
				new StorageDefinition('site'),
				new LabelDefinition('Site'),
				null, new ReflexivityDefinition(), null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'route' => new KeyDefinition(
				new StorageDefinition('route'),
				new LabelDefinition('Route'),
				new ScopeDefinition('site'), new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'route_generator' => new KeyDefinition(
				new StorageDefinition('route_generator'),
				new LabelDefinition('Route Generator'),
				new ScopeDefinition('route', null, true), null, null, [
					'query' => new FieldDefinition(new LabelDefinition('Query'), 'text', true, false),
				]
			),
			'site_generator' => new KeyDefinition(
				new StorageDefinition('site_generator'),
				new LabelDefinition('Site Generator'),
				new ScopeDefinition('site', null, true), new ReflexivityDefinition(), null, [
					'query' => new FieldDefinition(new LabelDefinition('Query'), 'text', true, false),
				]
			),
			'menu' => new KeyDefinition(
				new StorageDefinition('menu'),
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