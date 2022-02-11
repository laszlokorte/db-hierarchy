<?php

namespace App\Hierarchy\Schema;

use App\Hierarchy\Schema\FieldType;

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

use App\Util\ResultFetcher;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

use PDO;

class RecursiveLoader {
	private array $fieldTypes;

	private $subSchemas;
	private $connectionCache = [];

	private array $icons = [ 
		'alert', 'archive', 'arrow-both', 'arrow-down', 'arrow-left', 'arrow-right', 
		'arrow-switch', 'arrow-up', 'beaker', 'bell', 'bell-slash', 'blocked', 'bold', 
		'book', 'bookmark', 'bookmark-slash', 'briefcase', 'broadcast', 'browser', 
		'bug', 'calendar', 'check', 'check-circle', 'check-circle-fill', 'checklist', 
		'chevron-down', 'chevron-left', 'chevron-right', 'chevron-up', 'circle', 
		'circle-slash', 'clippy', 'clock', 'code', 'code-review', 'code-square', 
		'codescan', 'codescan-checkmark', 'codespaces', 'columns', 'comment', 
		'comment-discussion', 'container', 'cpu', 'credit-card', 'cross-reference', 
		'dash', 'database', 'dependabot', 'desktop-download', 'device-camera', 
		'device-camera-video', 'device-desktop', 'device-mobile', 'diamond', 
		'diff', 'diff-added', 'diff-ignored', 'diff-modified', 'diff-removed', 
		'diff-renamed', 'dot', 'dot-fill', 'download', 'duplicate', 'ellipsis', 
		'eye', 'eye-closed', 'file', 'file-badge', 'file-binary', 'file-code', 
		'file-diff', 'file-directory', 'file-submodule', 'file-symlink-file', 
		'file-zip', 'filter', 'flame', 'fold', 'fold-down', 'fold-up', 'gear', 
		'gift', 'git-branch', 'git-commit', 'git-compare', 'git-merge', 
		'git-pull-request', 'git-pull-request-closed', 'git-pull-request-draft', 
		'globe', 'grabber', 'graph', 'hash', 'heading', 'heart', 'heart-fill', 
		'history', 'home', 'horizontal-rule', 'hourglass', 'hubot', 'image', 'inbox', 
		'infinity', 'info', 'issue-closed', 'issue-draft', 'issue-opened', 
		'issue-reopened', 'italic', 'kebab-horizontal', 'key', 'key-asterisk', 'law', 
		'light-bulb', 'link', 'link-external', 'list-ordered', 'list-unordered', 
		'location', 'lock', 'logo-gist', 'logo-github', 'mail', 'mark-github', 
		'markdown', 'megaphone', 'mention', 'meter', 'milestone', 'mirror', 'moon', 
		'mortar-board', 'multi-select', 'mute', 'no-entry', 'north-star', 'note', 
		'number', 'organization', 'package', 'package-dependencies', 
		'package-dependents', 'paintbrush', 'paper-airplane', 'pencil', 
		'people', 'person', 'person-add', 'pin', 'play', 'plug', 'plus', 
		'plus-circle', 'project', 'pulse', 'question', 'quote', 'reply', 'repo', 
		'repo-clone', 'repo-forked', 'repo-pull', 'repo-push', 'repo-template', 
		'report', 'rocket', 'rows', 'rss', 'ruby', 'screen-full', 'screen-normal', 
		'search', 'select-single', 'server', 'share', 'share-android', 'shield', 
		'shield-check', 'shield-lock', 'shield-x', 'sidebar-collapse',
		'sidebar-expand', 'sign-in', 'sign-out', 'skip', 'smiley', 'sort-asc', 
		'sort-desc', 'square', 'square-fill', 'squirrel', 'star', 'star-fill', 'stop', 
		'stopwatch', 'strikethrough', 'sun', 'sync', 'table', 'tag', 'tasklist', 
		'telescope', 'terminal', 'three-bars', 'thumbsdown', 'thumbsup', 'tools', 
		'trash', 'triangle-down', 'triangle-left', 'triangle-right', 'triangle-up', 
		'typography', 'unfold', 'unlock', 'unmute', 'unverified', 'upload', 'verified', 
		'versions', 'video', 'workflow', 'x', 'x-circle', 'x-circle-fill', 'zap', 
	];

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
			'svg' => new FieldType\SvgType(),
			'sql' => new FieldType\SqlType(),
			'icon' => new FieldType\IconType($this->icons),


			'timeRange' => new FieldType\RangeType(new FieldType\TimeType()),
			'dateRange' => new FieldType\RangeType(new FieldType\DateType()),
			'dateTimeRange' => new FieldType\RangeType(new FieldType\DateTimeType()),
			'integerRange' => new FieldType\RangeType(new FieldType\IntegerType()),
			'floatRange' => new FieldType\RangeType(new FieldType\FloatType()),
			'decimalRange' => new FieldType\RangeType(new FieldType\DecimalType()),
		];

		$this->testHierarchy = [
			'slug' => 'C15BBD3CA3C74843A2E260CF81ED307D',
			'label' => new LabelDefinition(
				'Testing', 
				'Testing', 
				null, 
				'checklist', 
				'darkred'
			),
		];
	}

	public function loadSchema(string $hierarchyName = 'system') {
		return new Hierarchy($this->loadDefinition($hierarchyName), $hierarchyName);
	}

	public function loadSubSchemas() {
		if($this->subSchemas === null) {
			try {
				$stmt = $this->baseConnection->prepare('SELECT slug, label_singular, label_plural, label_icon, label_color, label_description FROM hierarchy WHERE slug <> "" ORDER BY hierarchy.priority');
				$result = $stmt->execute();
				$rows = $result->fetchAll();

				$this->subSchemas = [];
				foreach ($rows as $row) {
					$this->subSchemas[] = [
						'slug' => $row['slug'],
						'label' => new LabelDefinition(
							$row['label_singular']?:ucfirst($row['slug']),
							$row['label_plural']?:null,
							$row['label_description'],
							$row['label_icon'],
							$row['label_color']
						),
					];
				}
				$this->subSchemas[] = $this->testHierarchy;

			} catch(\Exception) {
				$this->subSchemas = [$this->testHierarchy];
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
			} elseif($hierarchyName === 'C15BBD3CA3C74843A2E260CF81ED307D') {
				$this->definitionCache[$hierarchyName] = $this->loadTestDefinition();
			} else {
				$this->definitionCache[$hierarchyName] = $this->loadDynamicDefinition($hierarchyName);
			}
		}

		return $this->definitionCache[$hierarchyName];
	}

	public function loadHierarchyConnection(string $hierarchyName = 'system') {
		if($hierarchyName === 'system') {
			return $this->baseConnection;
		} elseif($hierarchyName === 'C15BBD3CA3C74843A2E260CF81ED307D') {
			return $this->baseConnection;
		} else {
			$stmt = $this->baseConnection->prepare('SELECT dsn FROM hierarchy WHERE :slug = slug');
			$stmt->bindValue('slug', $hierarchyName, ParameterType::STRING);
			$result = $stmt->execute();
			$dsn = $result->fetchOne();

			if($dsn===false) {
				throw new \Exception();
			}

			return $dsn ? DriverManager::getConnection([
				'pdo' => new PDO($dsn),
				'driver' => $dsn === 'sqlite::memory:' ? 'pdo_sqlite' : null,
			]) : $this->baseConnection;
		}
	}

	private function loadDynamicDefinition($hierarchyName) {
		$stmt = $this->baseConnection->prepare('SELECT HEX(id) AS id, slug, label_singular, label_plural, label_icon, label_color, label_description FROM hierarchy WHERE hierarchy.slug = :slug');
		$stmt->bindValue('slug', $hierarchyName, ParameterType::STRING);
		$result = $stmt->execute();

		$row = $result->fetch();

		if(!$row) {
			throw new \Exception();
		}

		$hierarchyId = $row['id'];

		$keyStmt = $this->baseConnection->prepare('
			SELECT 
				collection.slug AS slug,
				collection.table_name AS table_name,
				collection.pk_name AS pk_name,
				collection.pk_type AS pk_type,
				collection.label_singular AS label_singular,
				collection.label_plural AS label_plural,
				collection.label_description AS label_description,
				collection.label_icon AS label_icon,
				collection.label_color AS label_color,
				scope_definition.id AS scope_id,
				scope_definition.collection_id AS scope_collection_id,
				scope_definition.scope_column_name AS scope_column_name,
				scope_collection.slug AS scope_slug,
				reflexivity_definition.id AS reflexivity_id,
				reflexivity_definition.parent_name AS reflexivity_parent_column,
				reflexivity_definition.child_name AS reflexivity_child_column,
				reflexivity_definition.depth_name AS reflexivity_depth_column,
				order_definition.id AS order_id,
				order_definition.order_column_name AS order_column_name,
				order_definition.is_singleton AS order_singleton,
				collection.summary AS summary
			FROM collection
			LEFT JOIN reflexivity_definition 
			ON reflexivity_definition.collection_id = collection.id
			LEFT JOIN order_definition
			ON order_definition.collection_id = collection.id
			LEFT JOIN scope_definition 
			ON scope_definition.collection_id = collection.id
			LEFT JOIN collection scope_collection
			ON scope_definition.scope_key_ref = scope_collection.id
			WHERE collection.hierarchy_id = UNHEX(:hid) AND collection.slug <> ""
			');
		$keyStmt->bindValue('hid', $hierarchyId, ParameterType::STRING);
		$keyResult = $keyStmt->execute();

		$keyRows = $keyResult->fetchAll();

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
			WHERE collection.hierarchy_id = UNHEX(:hid) AND field.slug <> ""
		');
		$fieldStmt->bindValue('hid', $hierarchyId, ParameterType::STRING);
		$fieldResult = $fieldStmt->execute();
		$fieldRows = ResultFetcher::fetchGrouped($fieldResult);


		$keys = [];

		foreach ($keyRows as $keyRow) {
			$fields = [];

			foreach($fieldRows[$keyRow['slug']]??[] AS $fieldRow) {
				$fields[$fieldRow['slug']] = new FieldDefinition(
					new LabelDefinition(
						$fieldRow['label_singular']?:ucfirst($fieldRow['slug']),
						$fieldRow['label_plural']?:null,
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
					$keyRow['table_name']?:$keyRow['slug'],
					$keyRow['pk_name']?:'id',
					$keyRow['pk_type']
				),
				new LabelDefinition(
					$keyRow['label_singular']?:ucfirst($keyRow['slug']),
					$keyRow['label_plural']?:null,
					$keyRow['label_description']?:null,
					$keyRow['label_icon']?:null,
					$keyRow['label_color']?:null
				), 
				$keyRow['scope_id'] ? new ScopeDefinition(
					$keyRow['scope_slug'],
					$keyRow['scope_column_name']?:sprintf('%s_id', $keyRow['scope_slug'])
				) : null, 
				$keyRow['reflexivity_id'] ? new ReflexivityDefinition(
					$keyRow['reflexivity_parent_column']?:'parent',
					$keyRow['reflexivity_child_column']?:'child',
					$keyRow['reflexivity_depth_column']?:'depth'
				) : null, 
				$keyRow['order_id'] ? new OrderDefinition(
					$keyRow['order_column_name']?:($keyRow['order_singleton']?'priority':'singleton'),
					$keyRow['order_singleton']
				) : null, 
				$fields,
				SummaryDefinition::parseSegments($keyRow['summary'])
			);
		}

		$def = new SchemaDefinition(
			new LabelDefinition(
				$row['label_singular']?:ucfirst($hierarchyName), 
				$row['label_plural']?:null, 
				$row['label_description'], 
				$row['label_icon'], 
				$row['label_color']
			), 
			$keys, 
			$this->fieldTypes
		);

		$def->validate();

		return $def;
	}

	private function loadBaseDefinition() {
		$settings = ['accent_color' => null,'title' => null,'intro' => null];
		try {
			$stmt = $this->baseConnection->prepare('SELECT title, url, accent_color, intro FROM settings');
			$settingResult = $stmt->execute();

			$settingsRow = $settingResult->fetch();

			if($settingsRow) {
				$settings = array_merge($settings, $settingsRow);
			}
		} catch(\Exception $e) {
			
		}

		return new SchemaDefinition(
			new LabelDefinition(
				$settings['title']?:'Hierarchy Manager', 
				$settings['title']?:'Hierarchie Managers', 
				$settings['intro'], 
				'favicon', 
				$settings['accent_color']?:'#00805A'
			), [
			'hierarchy' => new KeyDefinition(
				new StorageDefinition('hierarchy'),
				new LabelDefinition(
					'Hierarchy', 
					'Hierarchies',
					'What is a hierarchy?',
					'archive'
				),
				null, null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(
						new LabelDefinition('Slug'), 
						'string', true, true, [], false
					),
					'dsn' => new FieldDefinition(
						new LabelDefinition('DSN','DSNs', 'What is?'), 
						'string', false, false, [], false),
					'label_singular' => new FieldDefinition(
						new LabelDefinition('Label'), 
						'string', false, false, ['autofillBy' => 'slug']),
					'label_plural' => new FieldDefinition(
						new LabelDefinition('Label Plural'), 
						'string', false, false, ['autofillBy' => 'slug', 'autofillSuffix' => 's'], false),
					'label_description' => new FieldDefinition(
						new LabelDefinition('Description'), 
						'text', false, false, [], false),
					'label_icon' => new FieldDefinition(
						new LabelDefinition('Icon'), 
						'icon', false, false),
					'label_color' => new FieldDefinition(
						new LabelDefinition('Color'), 
						'color', true, false),
				], SummaryDefinition::parseSegments('{slug}')
			),
			'setting' => new KeyDefinition(
				new StorageDefinition('settings'),
				new LabelDefinition('Settings', 'Custom', null, 'gear', null, 'Default'),
				null, null, new OrderDefinition(singleton: true), [
					'title' => new FieldDefinition(
						new LabelDefinition('Title'), 
						'string', false, false),
					'url' => new FieldDefinition(
						new LabelDefinition('Url'), 
						'url', false, false),
					'accent_color' => new FieldDefinition(
						new LabelDefinition('Accent Color'), 
						'color', false, false),
					'intro' => new FieldDefinition(
						new LabelDefinition('Introduction Text'), 
						'text', false, false),
				], SummaryDefinition::parseSegments('Settings')
			),
			'account' => new KeyDefinition(
				new StorageDefinition('account'),
				new LabelDefinition('Account', 'Accounts', null, 'people'),
				null, null, null, [
					'login' => new FieldDefinition(
						new LabelDefinition('Login'), 
						'string', true, true),
					'password' => new FieldDefinition(
						new LabelDefinition('Password'), 
						'hash', true, false),
					'full_name' => new FieldDefinition(
						new LabelDefinition('Full Name'), 
						'string', false, false),
					'email' => new FieldDefinition(
						new LabelDefinition(' E-mail'), 
						'email', false, false),
					'role' => new FieldDefinition(
						new LabelDefinition('Role', 'Roles', null, null, null, 'None'), 
						'reference', true, false, 
						['target' => 'role','style' => 'expanded', 'explicit' => true]),
				], SummaryDefinition::parseSegments('{login}')
			),
			'role' => new KeyDefinition(
				new StorageDefinition('role'),
				new LabelDefinition('Role', 'Roles', null, 'mortar-board'),
				null, null, null, [
					'title' => new FieldDefinition(
						new LabelDefinition('Title'), 
						'string', true, true),
					'is_admin' => new FieldDefinition(
						new LabelDefinition('Admin'), 
						'bool', true, false),
				], SummaryDefinition::parseSegments('{title}')
			),
			'hierarchy_permission' => new KeyDefinition(
				new StorageDefinition('hierarchy_permission'),
				new LabelDefinition('Hierarchy Permission', 'Hierarchy Permissions'),
				new ScopeDefinition('role'), null, null, [
					'hierarchy' => new FieldDefinition(
						new LabelDefinition('Hierarchy', 'Hierarchies'), 'reference', true, true, 
						['target' => 'hierarchy']),
					'type' => new FieldDefinition(
						new LabelDefinition('Type', 'Types'), 
						'enum', true, false, ['values' => ['permit', 'restrict'],'explicit' => true]),
				], SummaryDefinition::parseSegments('{hierarchy}/{type}')
			),
			'collection_permission' => new KeyDefinition(
				new StorageDefinition('collection_permission'),
				new LabelDefinition('Collection Permission', 'Collection Permissions'),
				new ScopeDefinition('role'), null, null, [
					'collection' => new FieldDefinition(
						new LabelDefinition('Collection', 'Collections'), 
						'reference', true, true, 
						['target' => 'collection']),
					'type' => new FieldDefinition(
						new LabelDefinition('Type', 'Types'), 
						'enum', true, false, 
						['values' => ['permit', 'restrict']]),
				], SummaryDefinition::parseSegments('{collection}/{type}')
			),
			'field_permission' => new KeyDefinition(
				new StorageDefinition('field_permission'),
				new LabelDefinition('Field Permission', 'Field Permissions'),
				new ScopeDefinition('role'), null, null, [
					'field' => new FieldDefinition(
						new LabelDefinition('Field', 'Fields'), 
						'reference', true, true,
						['target' => 'field']),
					'type' => new FieldDefinition(
						new LabelDefinition('Type', 'Types'), 
						'enum', true, false, 
						['values' => ['permit', 'restrict']]),
				], SummaryDefinition::parseSegments('{field}/{type}')
			),
			'collection' => new KeyDefinition(
				new StorageDefinition('collection'),
				new LabelDefinition('Collection'),
				new ScopeDefinition('hierarchy', null, true), null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(
						new LabelDefinition('Slug'), 'string', true, true, [], false),
					'label_singular' => new FieldDefinition(
						new LabelDefinition('Label'), 
						'string', false, false, ['autofillBy' => 'slug']),
					'label_plural' => new FieldDefinition(
						new LabelDefinition('Label Plural'), 
						'string', false, false, ['autofillBy' => 'slug', 'autofillSuffix' => 's'], false),
					'label_description' => new FieldDefinition(
						new LabelDefinition('Description'), 
						'text', false, false, [], false),
					'label_icon' => new FieldDefinition(
						new LabelDefinition('Icon'), 
						'icon', false, false),
					'label_color' => new FieldDefinition(
						new LabelDefinition('Color'), 
						'color', false, false),
					'summary' => new FieldDefinition(
						new LabelDefinition('Summary Template'), 
						'string', false, false, [], false),
					'table_name' => new FieldDefinition(
						new LabelDefinition('Table Name'), 
						'string', true, true, ['autofillBy' => 'slug'], false),
					'pk_type' => new FieldDefinition(
						new LabelDefinition('Primary Key Type'), 
						'enum', true, false, ['values' => ['serial','uuid','manual']], false),
					'pk_name' => new FieldDefinition(
						new LabelDefinition('Primary Key Column Name'), 
						'string', false, false, [], false),
				], SummaryDefinition::parseSegments('{slug}')
			),
			'scope_definition' => new KeyDefinition(
				new StorageDefinition('scope_definition'),
				new LabelDefinition('Scope', 'Yes', null, null, null, 'None'),
				new ScopeDefinition('collection', null, true), null, new OrderDefinition(singleton: true), [
					'scope_key' => new FieldDefinition(
						new LabelDefinition('Parent', 'Parents'), 
						'reference', true, false, 
						['target' => 'collection']),
					'scope_column_name' => new FieldDefinition(
						new LabelDefinition('Scope Column'), 
						'string', false, false),
					'can_change' => new FieldDefinition(
						new LabelDefinition('Can change'), 
						'bool', true, false),
					'isolating' => new FieldDefinition(
						new LabelDefinition('Is Isolating'), 
						'bool', true, false),
				], SummaryDefinition::parseSegments('{scope_key}')
			),
			'order_definition' => new KeyDefinition(
				new StorageDefinition('order_definition'),
				new LabelDefinition('Order', 'Yes', null, null, null, 'None'),
				new ScopeDefinition('collection'), null, new OrderDefinition(singleton: true), [

					'is_singleton' => new FieldDefinition(
						new LabelDefinition('Singleton'), 
						'bool', true, false),
					'order_column_name' => new FieldDefinition(
						new LabelDefinition('Order column Name'), 
						'string', true, false),
				], SummaryDefinition::parseSegments('')
			),
			'reflexivity_definition' => new KeyDefinition(
				new StorageDefinition('reflexivity_definition'),
				new LabelDefinition('Reflexivity', 'Yes', null, null, null, 'None'),
				new ScopeDefinition('collection'), null, new OrderDefinition(singleton: true), [
					'parent_name' => new FieldDefinition(
						new LabelDefinition('Parent Column'), 
						'string', false, false),
					'child_name' => new FieldDefinition(
						new LabelDefinition('Parent Column'), 
						'string', false, false),
					'depth_name' => new FieldDefinition(
						new LabelDefinition('Depth Column'), 
						'string', false, false),
					'closure_table_name' => new FieldDefinition(
						new LabelDefinition('Closure Table Name', null, 'For nested collections an addition table is needed.'), 
						'string', false, false),
					'can_change' => new FieldDefinition(
						new LabelDefinition('Can change parent'), 
						'bool', true, false),
				], SummaryDefinition::parseSegments('{$self/label}')
			),
			'field' => new KeyDefinition(
				new StorageDefinition('field'),
				new LabelDefinition('Field'),
				new ScopeDefinition('collection', null, true), null, new OrderDefinition('priority', 'DESC'), [
					'slug' => new FieldDefinition(
						new LabelDefinition('Slug'), 
						'string', true, true, [], false),
					'type' => new FieldDefinition(
						new LabelDefinition('Type'), 
						'enum', true, false, 
						['style' => 'compact', 'values' => array_keys($this->fieldTypes)

					]),
					'options' => new FieldDefinition(
						new LabelDefinition('Options'), 
						'json', false, false, [], false),
					'is_required' => new FieldDefinition(
						new LabelDefinition('Required'), 
						'bool', true, false, [], false),
					'is_unique' => new FieldDefinition(
						new LabelDefinition('Unique'), 
						'bool', true, false, [], false),
					'label_singular' => new FieldDefinition(
						new LabelDefinition('Label'), 
						'string', false, true, ['autofillBy' => 'slug']),
					'label_plural' => new FieldDefinition(
						new LabelDefinition('Label Plural'), 
						'string', false, true, ['autofillBy' => 'slug', 'autofillSuffix' => 's'], false),
					'label_description' => new FieldDefinition(
						new LabelDefinition('Description'), 
						'text', false, false, [], false),
					'label_icon' => new FieldDefinition(
						new LabelDefinition('Icon'), 
						'icon', false, false),
					'label_color' => new FieldDefinition(
						new LabelDefinition('Color'), 
						'color', false, false),
				], SummaryDefinition::parseSegments('{slug}')
			),

			'collection_extension' => new KeyDefinition(
				new StorageDefinition('collection_extension'),
				new LabelDefinition('Extension'),
				new ScopeDefinition('collection', null, true),  null, null, [
					'slug' => new FieldDefinition(
						new LabelDefinition('Slug'), 
						'string', true, true),
				], SummaryDefinition::parseSegments('{slug}')
			),

			'field_extension' => new KeyDefinition(
				new StorageDefinition('field_extension'),
				new LabelDefinition('Field'),
				new ScopeDefinition('collection_extension'),  null, null, [
					'slug' => new FieldDefinition(
						new LabelDefinition('Slug'), 
						'string', true, true),
					'field_ref' => new FieldDefinition(
						new LabelDefinition('Field'), 'reference', true, false, 
						['target' => 'field']),
				], SummaryDefinition::parseSegments('{slug}')
			),

		], $this->fieldTypes);
	}

	private function loadTestDefinition() {
		return new SchemaDefinition(
			$this->testHierarchy['label'], [
			'field_test' => new KeyDefinition(
				new StorageDefinition('my_test'),
				new LabelDefinition('Field Test', 'Field Tests', null, 'rows'),
				null, null, null, 
				array_combine(array_keys($this->fieldTypes), 
					array_map(fn($typeId) => 
						new FieldDefinition(
							new LabelDefinition(ucfirst($typeId)), 
							$typeId, false, false, ['values' => ['x','y','z'], 'target' => 'aa'])
						, array_keys($this->fieldTypes))
				),
				SummaryDefinition::parseSegments('{$self/id}')
			),

			'aa' => new KeyDefinition(
				new StorageDefinition('aa'),
				new LabelDefinition('ScopeTest',null,null,'codescan'),
				null, new ReflexivityDefinition(), null, [],
				SummaryDefinition::parseSegments('aa')
			),
			'bb' => new KeyDefinition(
				new StorageDefinition('bb'),
				new LabelDefinition('bb'),
				new ScopeDefinition('aa',null, true), new ReflexivityDefinition(), null, [],
				SummaryDefinition::parseSegments('bb')
			),
			'cc' => new KeyDefinition(
				new StorageDefinition('cc'),
				new LabelDefinition('cc'),
				new ScopeDefinition('bb',null, true), null, null, [],
				SummaryDefinition::parseSegments('cc')
			),
			'dd' => new KeyDefinition(
				new StorageDefinition('dd'),
				new LabelDefinition('dd'),
				new ScopeDefinition('cc', null, true), null, null, [],
				SummaryDefinition::parseSegments('dd')
			),
			'ee' => new KeyDefinition(
				new StorageDefinition('ee'),
				new LabelDefinition('ee'),
				new ScopeDefinition('dd',null, true), null, null, [],
				SummaryDefinition::parseSegments('ee')
			),
			'ff' => new KeyDefinition(
				new StorageDefinition('ff'),
				new LabelDefinition('ff'),
				new ScopeDefinition('ee',null, true), null, null, [],
				SummaryDefinition::parseSegments('ff')
			),
			'xx' => new KeyDefinition(
				new StorageDefinition('xx'),
				new LabelDefinition('xx'),
				new ScopeDefinition('ff',null, true), null, null, [
					'zzref' => new FieldDefinition(
						new LabelDefinition('zzref'), 'reference', false, false, 
						['target' => 'zz']),
				],
				SummaryDefinition::parseSegments('xx')
			),
			'yy' => new KeyDefinition(
				new StorageDefinition('yy'),
				new LabelDefinition('yy'),
				new ScopeDefinition('xx',null, true), null, null, [],
				SummaryDefinition::parseSegments('yy')
			),
			'zz' => new KeyDefinition(
				new StorageDefinition('zz'),
				new LabelDefinition('zz'),
				new ScopeDefinition('yy', null, true), null, null, [],
				SummaryDefinition::parseSegments('zz')
			),
			'ww' => new KeyDefinition(
				new StorageDefinition('ww'),
				new LabelDefinition('ww'),
				new ScopeDefinition('zz',null, true), null, null, [],
				SummaryDefinition::parseSegments('ww')
			),
			'uu' => new KeyDefinition(
				new StorageDefinition('uu'),
				new LabelDefinition('uu'),
				new ScopeDefinition('ww', null, true), null, null, [
					'yyref' => new FieldDefinition(
						new LabelDefinition('yyref'), 'reference', false, false, 
						['target' => 'yy']),
				],
				SummaryDefinition::parseSegments('uu')
			),
			'pp' => new KeyDefinition(
				new StorageDefinition('pp'),
				new LabelDefinition('pp'),
				new ScopeDefinition('ff',null, true), null, null, [],
				SummaryDefinition::parseSegments('pp')
			),
			'qq' => new KeyDefinition(
				new StorageDefinition('qq'),
				new LabelDefinition('qq'),
				new ScopeDefinition('pp',null, true), null, null, [],
				SummaryDefinition::parseSegments('qq')
			),
			'rr' => new KeyDefinition(
				new StorageDefinition('rr'),
				new LabelDefinition('rr'),
				new ScopeDefinition('qq',null, true), null, null, [
					'uuref' => new FieldDefinition(
						new LabelDefinition('uuref'), 'reference', false, false, 
						['target' => 'uu']),
				],
				SummaryDefinition::parseSegments('rr')
			),
		], $this->fieldTypes);
	}
}