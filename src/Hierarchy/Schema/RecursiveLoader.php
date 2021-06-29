<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\FieldType;
use Doctrine\DBAL\Connection;
use App\Hierarchy\Storage\Relational\StorageConnection;
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Schema\Definition\LabelDefinition;
use App\Hierarchy\Schema\Definition\OrderDefinition;
use App\Hierarchy\Schema\Definition\ReflexivityDefinition;
use App\Hierarchy\Schema\Definition\KeyDefinition;
use App\Hierarchy\Schema\Definition\StorageDefinition;
use App\Hierarchy\Schema\Definition\FieldDefinition;
use App\Hierarchy\Schema\Definition\SummaryDefinition;
use App\Hierarchy\Schema\Definition\ScopeDefinition;

class RecursiveLoader {
	private array $fieldTypes;

	private $subSchemas;
	private $connectionCache = [];

	public function __construct(Connection $baseConnection) {
		$this->baseConnection = $baseConnection;

		$this->fieldTypes = [
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
			'email' => new FieldType\EmailType(),
			'color' => new FieldType\ColorType(),
			'geo' => new FieldType\GeolocationType(),
			'url' => new FieldType\UrlType(),


			'timeRange' => new FieldType\RangeType(new FieldType\TimeType()),
			'dateRange' => new FieldType\RangeType(new FieldType\DateType()),
			'dateTimeRange' => new FieldType\RangeType(new FieldType\DateTimeType()),
			'integerRange' => new FieldType\RangeType(new FieldType\IntegerType()),
			'floatRange' => new FieldType\RangeType(new FieldType\FloatType()),
			'decimalRange' => new FieldType\RangeType(new FieldType\DecimalType()),
		];
	}

	public function loadSchema(string $hierarchyName = 'system') {
		return new Hierarchy($this->loadDefinition($hierarchyName), $hierarchyName);
	}

	public function loadSubSchemas() {
		if($this->subSchemas === null) {
			try {
				$stmt = $this->baseConnection->prepare('SELECT slug, label_singular, label_plural, label_icon, label_color, label_description FROM hierarchy');
				$stmt->execute();

				$this->subSchemas = $stmt->fetchAll();
			} catch(\Exception) {
				$this->subSchemas = [];
			}
		}
		

		return $this->subSchemas;
	}

	public function loadStorageConnection(string $hierarchyName = 'system') {
		if(empty($this->connectionCache[$hierarchyName])) {
			$this->connectionCache[$hierarchyName] = new StorageConnection(
				$this->loadDefinition($hierarchyName), 
				$this->loadHierarchyConnection($hierarchyName)
			);
		}

		return $this->connectionCache[$hierarchyName];
	}

	public function loadDefinition(string $hierarchyName = 'system') {
		if(empty($this->definitionCache[$hierarchyName])) {
			 if($hierarchyName === 'system') {
				$this->definitionCache[$hierarchyName] = $this->loadBaseDefinition();
			} else {
				$this->definitionCache[$hierarchyName] = $this->loadDynamicDefinition($hierarchyName);
			}
		}

		return $this->definitionCache[$hierarchyName];
	}

	public function loadHierarchyConnection(string $hierarchyName = 'system') {
		if($hierarchyName === 'system') {
			return $this->baseConnection;
		} else {
			$stmt = $this->baseConnection->prepare('SELECT dsn FROM hierarchy WHERE :slug = slug');
			$stmt->bindValue('slug', $hierarchyName, \PDO::PARAM_STR);
			$stmt->execute();
			$dsn = $stmt->fetchColumn();

			if($dsn===false) {
				throw new \Exception();
			}

			return $dsn ? \Doctrine\DBAL\DriverManager::getConnection(['pdo' => new \PDO($dsn)]) : $this->baseConnection;
		}
	}

	private function loadDynamicDefinition($hierarchyName) {
		$stmt = $this->baseConnection->prepare('SELECT id, slug, label_singular, label_plural, label_icon, label_color, label_description FROM hierarchy WHERE hierarchy.slug = :slug');
		$stmt->bindValue('slug', $hierarchyName, \PDO::PARAM_STR);
		$stmt->execute();

		$row = $stmt->fetch();

		if(!$row) {
			throw new \Exception();
		}

		$hierarchyId = $row['id'];

		$keyStmt = $this->baseConnection->prepare('
			SELECT 
				collection.slug AS slug,
				collection.table_name AS table_name,
				collection.pk_name AS pk_name,
				collection.label_singular AS label_singular,
				collection.label_plural AS label_plural,
				collection.label_description AS label_description,
				collection.label_icon AS label_icon,
				collection.label_color AS label_color,
				scope.id AS scope_id,
				scope.collection_id AS scope_collection_id,
				scope.scope_column_name AS scope_column_name,
				scope_collection.slug AS scope_slug,
				reflexivity.id AS reflexivity_id,
				reflexivity.parent_name AS reflexivity_parent_column,
				reflexivity.child_name AS reflexivity_child_column,
				reflexivity.depth_name AS reflexivity_depth_column,
				"order".id AS order_id,
				"order".order_column_name AS order_column_name,
				"order".is_singleton AS order_singleton,
				collection.summary AS summary
			FROM collection
			LEFT JOIN reflexivity 
			ON reflexivity.collection_id = collection.id
			LEFT JOIN "order"
			ON "order".collection_id = collection.id
			LEFT JOIN scope 
			ON scope.collection_id = collection.id
			LEFT JOIN collection scope_collection
			ON scope.scope_key_ref = scope_collection.id
			WHERE collection.hierarchy_id = :hid
			');
		$keyStmt->bindValue('hid', $hierarchyId, \PDO::PARAM_INT);
		$keyStmt->execute();
		$keyRows = $keyStmt->fetchAll();

		$fieldStmt = $this->baseConnection->prepare('
			SELECT collection.slug AS collection_slug, 
				field.slug AS slug,
				field.label_singular AS label_singular,
				field.label_plural AS label_plural,
				field.label_description AS label_description,
				field.label_icon AS label_icon,
				field.label_color AS label_color,
				field.type AS type,
				field.is_required AS is_required,
				field.is_unique AS is_unique,
				field.options AS options 
			FROM field 
			INNER JOIN collection 
			ON collection.id = field.collection_id 
			WHERE collection.hierarchy_id = :hid
		');
		$fieldStmt->bindValue('hid', $hierarchyId, \PDO::PARAM_INT);
		$fieldStmt->execute();
		$fieldRows = $fieldStmt->fetchAll(\PDO::FETCH_GROUP);

		$keys = [];

		foreach ($keyRows as $keyRow) {
			$fields = [];

			foreach($fieldRows[$keyRow['slug']]??[] AS $fieldRow) {
				$fields[$fieldRow['slug']] = new FieldDefinition(
					new LabelDefinition(
						$fieldRow['label_singular'],
						$fieldRow['label_plural'],
						$fieldRow['label_description'],
						$fieldRow['label_icon'],
						$fieldRow['label_color']
					), 
					$fieldRow['type'], 
					$fieldRow['is_required'], 
					$fieldRow['is_unique'], 
					json_decode($fieldRow['options'], true)??[]
				);
			}

			$keys[$keyRow['slug']] = new KeyDefinition(
				new StorageDefinition(
					$keyRow['table_name'],
					$keyRow['pk_name']?:null
				),
				new LabelDefinition(
					$keyRow['label_singular']?:null,
					$keyRow['label_plural']?:null,
					$keyRow['label_description']?:null,
					$keyRow['label_icon']?:null,
					$keyRow['label_color']?:null
				), 
				$keyRow['scope_id'] ? new ScopeDefinition(
					$keyRow['scope_slug'],
					$keyRow['scope_column_name']?:null
				) : null, 
				$keyRow['reflexivity_id'] ? new ReflexivityDefinition(
					$keyRow['reflexivity_parent_column']?:'parent',
					$keyRow['reflexivity_child_column']?:'child',
					$keyRow['reflexivity_depth_column']?:'depth'
				) : null, 
				$keyRow['order_id'] ? new OrderDefinition(
					$keyRow['order_column_name']?:null,
					$keyRow['order_singleton']
				) : null, 
				$fields,
				SummaryDefinition::parseString($keyRow['summary'])
			);
		}

		return new SchemaDefinition(
			new LabelDefinition($row['label_singular'], $row['label_plural'], $row['label_description'], $row['label_icon'], $row['label_color']), $keys, $this->fieldTypes);
	}

	private function loadBaseDefinition() {
		try {
			$stmt = $this->baseConnection->prepare('SELECT title, url, accent_color FROM settings');
			$stmt->execute();

			$settings = $stmt->fetch();
		} catch(\Exception $e) {
			$settings = ['accent_color' => null];
		}

		return new SchemaDefinition(
			new LabelDefinition('Hierarchy Manager', 'Hierarchie Managers', null, null, $settings['accent_color']?:'#444'), [
			'hierarchy' => new KeyDefinition(
				new StorageDefinition('hierarchy'),
				new LabelDefinition('Hierarchy', 'Hierarchies'),
				null, null, null, [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
					'dsn' => new FieldDefinition(new LabelDefinition('DSN'), 'string', true, false),
					'label_singular' => new FieldDefinition(new LabelDefinition('Label Singular'), 'string', true, false),
					'label_plural' => new FieldDefinition(new LabelDefinition('Label Plural'), 'string', true, false),
					'label_description' => new FieldDefinition(new LabelDefinition('Description'), 'text', true, false),
					'label_icon' => new FieldDefinition(new LabelDefinition('Icon'), 'enum', false, false, ['values' => [], 'style' => 'compact']),
					'label_color' => new FieldDefinition(new LabelDefinition('Color'), 'color', true, false),
				], SummaryDefinition::parseString('{label_singular}')
			),
			'setting' => new KeyDefinition(
				new StorageDefinition('settings'),
				new LabelDefinition('Settings', 'Settings'),
				null, null, new OrderDefinition(singleton: true), [
					'title' => new FieldDefinition(new LabelDefinition('Title'), 'string', false, false),
					'url' => new FieldDefinition(new LabelDefinition('Url'), 'url', false, false),
					'accent_color' => new FieldDefinition(new LabelDefinition('Accent Color'), 'color', false, false),
				], SummaryDefinition::parseString('settings')
			),
			'account' => new KeyDefinition(
				new StorageDefinition('account'),
				new LabelDefinition('Account', 'Accounts'),
				null, null, null, [
					'login' => new FieldDefinition(new LabelDefinition('Login'), 'string', true, true),
					'password' => new FieldDefinition(new LabelDefinition('Password'), 'hash', true, false),
					'full_name' => new FieldDefinition(new LabelDefinition('Full Name'), 'string', false, false),
					'email' => new FieldDefinition(new LabelDefinition(' E-mail'), 'email', false, false),
					'role' => new FieldDefinition(new LabelDefinition('Role', 'Roles'), 'reference', false, false, ['target' => 'role','style' => 'expanded']),
				], SummaryDefinition::parseString('{login}')
			),
			'role' => new KeyDefinition(
				new StorageDefinition('role'),
				new LabelDefinition('Role', 'Roles'),
				null, null, null, [
					'title' => new FieldDefinition(new LabelDefinition('Title'), 'string', true, true),
					'is_admin' => new FieldDefinition(new LabelDefinition('Admin'), 'bool', true, false),
				], SummaryDefinition::parseString('{title}')
			),
			'hierarchy_permission' => new KeyDefinition(
				new StorageDefinition('hierarchy_permission'),
				new LabelDefinition('Hierarchy Permission', 'Hierarchy Permissions'),
				new ScopeDefinition('role'), null, null, [
					'hierarchy' => new FieldDefinition(new LabelDefinition('Hierarchy', 'Hierarchies'), 'reference', true, true, ['target' => 'hierarchy']),
					'type' => new FieldDefinition(new LabelDefinition('Type', 'Types'), 'enum', true, false, ['values' => ['permit', 'restrict']]),
				], SummaryDefinition::parseString('{hierarchy}/{type}')
			),
			'collection_permission' => new KeyDefinition(
				new StorageDefinition('collection_permission'),
				new LabelDefinition('Collection Permission', 'Collection Permissions'),
				new ScopeDefinition('role'), null, null, [
					'collection' => new FieldDefinition(new LabelDefinition('Collection', 'Collections'), 'reference', true, true, ['target' => 'collection']),
					'type' => new FieldDefinition(new LabelDefinition('Type', 'Types'), 'enum', true, false, ['values' => ['permit', 'restrict']]),
				], SummaryDefinition::parseString('{collection}/{type}')
			),
			'field_permission' => new KeyDefinition(
				new StorageDefinition('field_permission'),
				new LabelDefinition('Field Permission', 'Field Permissions'),
				new ScopeDefinition('role'), null, null, [
					'field' => new FieldDefinition(new LabelDefinition('Field', 'Fields'), 'reference', true, true, ['target' => 'field']),
					'type' => new FieldDefinition(new LabelDefinition('Type', 'Types'), 'enum', true, false, ['values' => ['permit', 'restrict']]),
				], SummaryDefinition::parseString('{field}/{type}')
			),
			'collection' => new KeyDefinition(
				new StorageDefinition('collection'),
				new LabelDefinition('Collection'),
				new ScopeDefinition('hierarchy'), null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
					'label_singular' => new FieldDefinition(new LabelDefinition('Label Singular'), 'string', true, false),
					'label_plural' => new FieldDefinition(new LabelDefinition('Label Plural'), 'string', true, false),
					'label_description' => new FieldDefinition(new LabelDefinition('Description'), 'text', true, false),
					'label_icon' => new FieldDefinition(new LabelDefinition('Icon'), 'enum', false, false,  ['values' => [], 'style' => 'compact']),
					'label_color' => new FieldDefinition(new LabelDefinition('Color'), 'string', true, false),
					'summary' => new FieldDefinition(new LabelDefinition('Summary Template'), 'string', true, false),
					'table_name' => new FieldDefinition(new LabelDefinition('Table Name'), 'string', true, false),
					'pk_name' => new FieldDefinition(new LabelDefinition('Primary Key Column Name'), 'string', true, false),
				], SummaryDefinition::parseString('{label_singular}')
			),
			'scope_definition' => new KeyDefinition(
				new StorageDefinition('scope'),
				new LabelDefinition('Scope', 'Scopes'),
				new ScopeDefinition('collection'), null, new OrderDefinition(singleton: true), [
					'scope_key' => new FieldDefinition(new LabelDefinition('Parent', 'Parents'), 'reference', true, false, ['target' => 'collection']),
					'scope_column_name' => new FieldDefinition(new LabelDefinition('Scope Column'), 'string', true, false),
				], SummaryDefinition::parseString('')
			),
			'order_definition' => new KeyDefinition(
				new StorageDefinition('order'),
				new LabelDefinition('Order', 'Order'),
				new ScopeDefinition('collection'), null, new OrderDefinition(singleton: true), [

					'is_singleton' => new FieldDefinition(new LabelDefinition('Singleton'), 'bool', true, false),
					'order_column_name' => new FieldDefinition(new LabelDefinition('Order column Name'), 'string', true, false),
				], SummaryDefinition::parseString('')
			),
			'reflexivity_definition' => new KeyDefinition(
				new StorageDefinition('reflexivity'),
				new LabelDefinition('Reflexivity', 'Reflexivity'),
				new ScopeDefinition('collection'), null, new OrderDefinition(singleton: true), [
					'parent_name' => new FieldDefinition(new LabelDefinition('Parent Column'), 'string', true, false),
					'child_name' => new FieldDefinition(new LabelDefinition('Parent Column'), 'string', true, false),
					'depth_name' => new FieldDefinition(new LabelDefinition('Depth Column'), 'string', true, false),
				], SummaryDefinition::parseString('')
			),
			'field' => new KeyDefinition(
				new StorageDefinition('field'),
				new LabelDefinition('Field'),
				new ScopeDefinition('collection'), null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(new LabelDefinition('Slug'), 'string', true, false),
					'type' => new FieldDefinition(new LabelDefinition('Type'), 'enum', true, false, ['style' => 'compact', 'values' => array_keys($this->fieldTypes)

					]),
					'options' => new FieldDefinition(new LabelDefinition('Options'), 'json', true, false),
					'is_required' => new FieldDefinition(new LabelDefinition('Required'), 'bool', true, false),
					'is_unique' => new FieldDefinition(new LabelDefinition('Unique'), 'bool', true, false),
					'label_singular' => new FieldDefinition(new LabelDefinition('Label Singular'), 'string', true, false),
					'label_plural' => new FieldDefinition(new LabelDefinition('Label Plural'), 'string', true, false),
					'label_description' => new FieldDefinition(new LabelDefinition('Description'), 'text', true, false),
					'label_icon' => new FieldDefinition(new LabelDefinition('Icon'), 'enum', false, false, ['style' => 'compact', 'values' => []]),
					'label_color' => new FieldDefinition(new LabelDefinition('Color'), 'string', true, false),
				], SummaryDefinition::parseString('{label_singular}')
			),
		], $this->fieldTypes);
	}
}