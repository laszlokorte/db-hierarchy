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
			'essen' => new KeyDefinition(
				new StorageDefinition('essen'),
				new LabelDefinition('Essen',"Essen"),
				null, null, null, [
					'name' => new FieldDefinition(new LabelDefinition('Name'), 'text', true, false),
					'bewertung' => new FieldDefinition(new LabelDefinition('Bewertung'), 'text', true, false),
				]
			),

			'zutaten' => new KeyDefinition(
				new StorageDefinition('zutaten'),
				new LabelDefinition('Zutat',"Zutaten"),
				new ScopeDefinition('essen', null), null, null, [
					'name' => new FieldDefinition(new LabelDefinition('Name'), 'text', true, false),
				]
			),
			'site_generator' => new KeyDefinition(
				new StorageDefinition('site_generator'),
				new LabelDefinition('Site Generator'),
				new ScopeDefinition('site', null, true), new ReflexivityDefinition(), null, [
					'query' => new FieldDefinition(new LabelDefinition('Query'), 'text', true, false),
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
			'content' => new KeyDefinition(
				new StorageDefinition('content'),
				new LabelDefinition('Content'),
				new ScopeDefinition('route'), new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'content_generator' => new KeyDefinition(
				new StorageDefinition('content_generator'),
				new LabelDefinition('Content Generator'),
				new ScopeDefinition('content', null, true), null, null, [
					'query' => new FieldDefinition(new LabelDefinition('Query'), 'text', true, false),
				]
			),
			'menu' => new KeyDefinition(
				new StorageDefinition('menu'),
				new LabelDefinition('Menu'),
				null, null, null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'menu_item' => new KeyDefinition(
				new StorageDefinition('menu_item'),
				new LabelDefinition('Menu Item'),
				new ScopeDefinition('menu'), new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'resource_directory' => new KeyDefinition(
				new StorageDefinition('resource_directory'),
				new LabelDefinition('Directory', 'Directories'),
				null, new ReflexivityDefinition(), null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'resource' => new KeyDefinition(
				new StorageDefinition('resource'),
				new LabelDefinition('Resource'),
				new ScopeDefinition('resource_directory'), null, null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'example_parent' => new KeyDefinition(
				new StorageDefinition('example_parent'),
				new LabelDefinition('Exmp Parent'),
				null, null, null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'example_child' => new KeyDefinition(
				new StorageDefinition('example_child'),
				new LabelDefinition('Exmp Parent'),
				new ScopeDefinition('example_parent'), null, null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'sorted_parent' => new KeyDefinition(
				new StorageDefinition('sorted_parent'),
				new LabelDefinition('Sorted Parent'),
				null, null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'sorted_child' => new KeyDefinition(
				new StorageDefinition('sorted_child'),
				new LabelDefinition('Sorted Child'),
				new ScopeDefinition('sorted_parent'), null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'sorted_tree' => new KeyDefinition(
				new StorageDefinition('sorted_tree'),
				new LabelDefinition('Sorted Tree'),
				null, new ReflexivityDefinition(), new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'upload' => new KeyDefinition(
				new StorageDefinition('upload'),
				new LabelDefinition('Upload'),
				null, null, null, [
					'file' => new FieldDefinition(new LabelDefinition('File'), 'file', true, false),
				]
			),
			'grid' => new KeyDefinition(
				new StorageDefinition('grid'),
				new LabelDefinition('Grid'),
				null, null, null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'grid_column' => new KeyDefinition(
				new StorageDefinition('grid_column'),
				new LabelDefinition('Grid Column'),
				new ScopeDefinition('grid'), null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
			'grid_row' => new KeyDefinition(
				new StorageDefinition('grid_row'),
				new LabelDefinition('Grid Row'),
				new ScopeDefinition('grid'), null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'text', true, false),
				]
			),
		],
		[
			'text' => new FieldType\TextType(),
			'file' => new FieldType\FileType(),
		]);
	}
}