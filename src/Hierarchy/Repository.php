<?php

namespace App\Hierarchy;

use Doctrine\DBAL\Connection;

use App\Hierarchy\Schema\SchemaRoot;

class Repository {
	public function __construct(public Connection $db, Definition $definition, SchemaRoot $schema) {
		$this->db = $db;
		$this->definition = $definition;
	}

	public function getRootKeys() {
		return $this->definition->getRootKeys();
	}

	public function getChildKeys($parentKey) {
		return $this->definition->getChildKeys($parentKey);
	}

	public function getAllFields() {
		return $this->definition->getAllFields();
	}

	public function getChildFields($parentKey) {
		return $this->definition->getChildFields($parentKey);
	}

	public function getFields($key) {
		return $this->definition->getFields($key);
	}

	public function getParentKey($key) {
		return $this->definition->getParentKey($key);
	}

	public function isMoveable($key) {
		return $this->definition->isMoveable($key);
	}

	public function isScoped($key) {
		return $this->definition->isScoped($key);
	}

	public function isReflexive($key) {
		return $this->definition->isReflexive($key);
	}

	public function getKeyOrder($key) {
		return $this->definition->getKeyOrder($key);
	}

	public function loadMoveTargets($key, $id) {
		if(!$this->isMoveable($key)) {
			return null;
		}

		$result = [];
		$targets = [];
		$outerKey = NULL;
		$baseKey = $key;

		if($this->isReflexive($key)) {
			$targets[$key] = $this->loadPartialHierarchy($key);
		}
		if($this->isScoped($key)) {
			$targets[$this->getParentKey($key)] = $this->loadPartialHierarchy($this->getParentKey($key));
			$baseKey = $this->getParentKey($key);
		} else {
			$result[] = ['depth' => 0, 'value' => '', 'label' => '/'];
		}

		$walk = function($hierarchyKey, $scope, $parent, $depth, $self) use ($targets, $key, $id, &$result) {
			$items = $targets[$hierarchyKey][$scope.'/'.$parent] ?? [];

			foreach ($items as $item) {
				if($key==$hierarchyKey && $item['id']==$id) {
					continue;
				}

				$value = ($key==$hierarchyKey) ? $scope.'/'.$item['id'] : $item['id'];

				$result[] = ['depth' => $depth, 'value' => $value, 'label' => sprintf('%s-%d', $hierarchyKey, $item['id'])];
				$self($hierarchyKey, $scope, $item['id'], $depth + 1, $self);

				foreach ($this->definition->structure as $k => $conf) {
					if($conf['parent'] !== $hierarchyKey) {
						continue;
					}
					$self($k, $item['id'], '', $depth + 1, $self);
				}
			}
		};

		$rows = $targets[$baseKey];
		foreach ($rows as $index => $item) {
			[$itemScope, $itemId] = explode('/', $index, 2);
			
			if($itemId) {
				continue;
			}

			$walk($baseKey, $itemScope, '', 0, $walk);
		}

		return $result;
	}

	public function loadKeyClosureDefects($key) {
		$reflexive = $this->definition->structure[$key]['reflexive'];

		$checkInvalid = $this->buildQuery(<<<SQL
			SELECT * FROM %s_closure_invalid
		SQL, $key, $key);
		$checkInvalid->execute();
		$affectedInvalid = $checkInvalid->fetchAll();

		$checkMissing = $this->buildQuery(<<<SQL
			SELECT * FROM %s_closure_missing
		SQL, $key, $key);
		$checkMissing->execute();
		$affectedMissing = $checkMissing->fetchAll();
		
		return [
			'invalid' => $affectedInvalid,
			'missing' => $affectedMissing,
		];
	}

	public function loadAllClosureDefects() {
		$result = [];

		foreach ($this->definition->structure as $key => $parent) {
			if(!$parent['reflexive']) {
				continue;
			}

			$result[$key] = $this->loadKeyClosureDefects($key);
		}

		return $result;
	}

	public function repairKeyClosureDefects($key, $limit) {
		if(!$this->definition->structure[$key]['reflexive']) {
			return;
		}

		$parent = $this->definition->structure[$key]['parent'];


		$checkInvalid = $this->buildQuery(<<<SQL
			DELETE FROM %s_closure WHERE id IN (SELECT id FROM %s_closure_invalid)
		SQL, $key, $key);

		if($parent) {
			$checkMissing = $this->buildQuery(<<<SQL
				INSERT INTO %s_closure (%s_id, parent_id,child_id,depth) SELECT %s_id, parent_id, child_id, depth FROM %s_closure_missing;
				SQL, $key, $parent, $parent, $key);
		} else {
			$checkMissing = $this->buildQuery(<<<SQL
				INSERT INTO %s_closure (parent_id,child_id,depth) SELECT parent_id, child_id, depth FROM %s_closure_missing;
				SQL, $key, $key);
		}

		do {
			if($limit-- < 0) {
				throw new ConsistencyException("limit reached");
			}

			$dirty = false;

			$checkInvalid->execute();
			$affectedInvalid = $checkInvalid->rowCount();
			if($affectedInvalid) {
				$dirty = true;
			}

			$checkMissing->execute();
			$affectedMissing = $checkMissing->rowCount();

			if($affectedMissing) {
				$dirty = true;
			}

		} while($dirty);
	}

	public function repairAllClosureDefects($limit) {
		foreach ($this->definition->structure as $key => $parent) {
			if(!$parent['reflexive']) {
				continue;
			}
			
			$this->repairKeyClosureDefects($key, $limit);
		}
	}

	public function createSchema() {
		$sorted = $this->definition->topoSorted();

		$this->db->beginTransaction();

		try {
			foreach (array_reverse($sorted) as $key) {
				$parent = $this->definition->structure[$key];


				if($parent['order']) {
					$dropNormalizedOrderView = $this->buildQuery(
						sprintf("DROP VIEW IF EXISTS %s_normalized_order;", $key)
					);
					$dropNormalizedOrderView->execute();
				}

				if($parent['reflexive']) {

					$dropHierarchyView = $this->buildQuery(
						sprintf("DROP VIEW IF EXISTS %s_hierarchy;", $key)
					);
					$dropHierarchyView->execute();
					$dropCheckMissingView = $this->buildQuery(
						sprintf("DROP VIEW IF EXISTS %s_closure_missing;",$key)
					);
					$dropCheckMissingView->execute();
					$dropCheckInvalidView = $this->buildQuery(
						sprintf("DROP VIEW IF EXISTS %s_closure_invalid;", $key)
					);
					$dropCheckInvalidView->execute();

					$dropClosure = $this->buildQuery(
						sprintf('DROP TABLE IF EXISTS "%s_closure";', $key)
					);
					$dropClosure->execute();
				}

				$dropTable = $this->buildQuery(
					sprintf('DROP TABLE IF EXISTS "%s";', $key)
				);
				$dropTable->execute();
			}

			foreach ($sorted as $key) {
				$parent = $this->definition->structure[$key];
				$createTable = $this->buildQuery(
					$this->tableSQL($key, $parent)
				);
				$createTable->execute();

				if($parent['reflexive']) {
					$createClosure = $this->buildQuery(
						$this->closureSQL($key, $parent['parent'])
					);
					$createClosure->execute();

					$createHierarchyView = $this->buildQuery(
						sprintf("CREATE VIEW IF NOT EXISTS %s_hierarchy AS %s;",
						$key,
						$this->hierarchySQL($key, $parent['parent'], $parent['order']))
					);
					$createHierarchyView->execute();
					$createCheckMissingView = $this->buildQuery(
						sprintf("CREATE VIEW IF NOT EXISTS %s_closure_missing AS %s;",
						$key,
						$this->checkMissingSQL($key, $parent['parent']))
					);
					$createCheckMissingView->execute();
					$createCheckInvalidView = $this->buildQuery(
						sprintf("CREATE VIEW IF NOT EXISTS %s_closure_invalid AS %s;",
						$key,
						$this->checkInvalidSQL($key, $parent['parent']))
					);
					$createCheckInvalidView->execute();		
				}
				if($parent['order']) {
					$createNormalizedOrderView = $this->buildQuery(
						sprintf("CREATE VIEW IF NOT EXISTS %s_normalized_order AS %s;",
						$key,
						$this->normalizedOrderSQL($key, $parent['order'], $parent['parent'], $parent['reflexive']))
					);
					$createNormalizedOrderView->execute();
				}
			}


			$this->db->commit();
		} catch (Exception $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	public function showSchema() {
		$sorted = $this->definition->topoSorted();
		$sql = '';
		foreach (array_reverse($sorted) as $key) {
			$parent = $this->definition->structure[$key];

			if($parent['order']) {
				$sql .= sprintf("DROP VIEW IF EXISTS %s_normalized_order;", $key) .PHP_EOL;
			}


			if($parent['reflexive']) {
				$sql .= sprintf("DROP VIEW IF EXISTS %s_hierarchy;", $key). PHP_EOL;
				$sql .= sprintf("DROP VIEW IF EXISTS %s_closure_missing;",$key). PHP_EOL;
				$sql .= sprintf("DROP VIEW IF EXISTS %s_closure_invalid;", $key). PHP_EOL;
				$sql .= sprintf('DROP TABLE IF EXISTS "%s_closure";', $key). PHP_EOL;
			}

			$sql .= sprintf('DROP TABLE IF EXISTS "%s";', $key). PHP_EOL;
		}

		foreach ($sorted as $key) {
			$parent = $this->definition->structure[$key];
			$sql .= $this->tableSQL($key, $parent);

			if($parent['reflexive']) {
				$this->closureSQL($key, $parent['parent']);

				$sql .= sprintf("CREATE VIEW IF NOT EXISTS %s_hierarchy AS %s;", $key,
				$this->hierarchySQL($key, $parent['parent'], $parent['order']));

				$sql .= sprintf("CREATE VIEW IF NOT EXISTS %s_closure_missing AS %s;", $key,
				$this->checkMissingSQL($key, $parent['parent']));

				$sql .= sprintf("CREATE VIEW IF NOT EXISTS %s_closure_invalid AS %s;", $key,
				$this->checkInvalidSQL($key, $parent['parent']));
			}
			if($parent['order']) {
				$sql .= sprintf("CREATE VIEW IF NOT EXISTS %s_normalized_order AS %s;", $key,
				$this->normalizedOrderSQL($key, $parent['order'], $parent['parent'], $parent['reflexive']));
			}
		}

		return $sql;
	}

	public function loadHierarchy() {
		$result = [];

		foreach ($this->definition->structure as $key => $parent) {
			if($parent['parent']) {
				if($parent['reflexive']) {
					$routeStmt = $this->buildQuery(<<<SQL
						SELECT (h.%s_id ||"/"||IFNULL(h.parent,"")), s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id
					SQL, $parent['parent'], $key, $key);
					$routeStmt->execute();
					$result[$key] = $routeStmt->fetchAll(\PDO::FETCH_GROUP);
				} else {
					$routeStmt = $this->buildQuery(<<<SQL
						SELECT %s_id||"/", * FROM %s
					SQL, $parent['parent'], $key);
					$routeStmt->execute();
					$result[$key] = $routeStmt->fetchAll(\PDO::FETCH_GROUP);
				}
			} else {
				if($parent['reflexive']) {
					$siteStmt = $this->buildQuery(<<<SQL
						SELECT "/"||IFNULL(h.parent,""), s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id
					SQL, $key, $key);
					$siteStmt->execute();
					$result[$key] = $siteStmt->fetchAll(\PDO::FETCH_GROUP);
				} else {
					$siteStmt = $this->buildQuery(<<<SQL
						SELECT "/", * FROM %s
					SQL, $key);
					$siteStmt->execute();
					$result[$key] = $siteStmt->fetchAll(\PDO::FETCH_GROUP);
				}
			}
		}

		return $result;
	}

	public function loadPartialHierarchy($key) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$reflexive = $self['reflexive'];

		if($parent) {
			if($reflexive) {
				$routeStmt = $this->buildQuery(<<<SQL
					SELECT (h.%s_id ||"/"||IFNULL(h.parent,"")), s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id
				SQL, $parent, $key, $key);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll(\PDO::FETCH_GROUP);
			} else {
				$routeStmt = $this->buildQuery(<<<SQL
					SELECT %s_id||"/", * FROM %s
				SQL, $parent, $key);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll(\PDO::FETCH_GROUP);
			}
		} else {
			if($reflexive) {
				$siteStmt = $this->buildQuery(<<<SQL
					SELECT "/"||IFNULL(h.parent,""), s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id
				SQL, $key, $key);
				$siteStmt->execute();
				$result = $siteStmt->fetchAll(\PDO::FETCH_GROUP);
			} else {
				$siteStmt = $this->buildQuery(<<<SQL
					SELECT "/", * FROM %s
				SQL, $key);
				$siteStmt->execute();
				$result = $siteStmt->fetchAll(\PDO::FETCH_GROUP);
			}
		}

		return $result;
	}

	public function loadRootNodes() {
		$results = [];

		foreach ($this->definition->structure as $key => $parent) {
			if($parent['parent']) {
				continue;
			}
			
			if($parent['reflexive']) {
				$siteStmt = $this->buildQuery(<<<SQL
					SELECT s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent IS NULL
				SQL, $key, $key);
				$siteStmt->execute();
				$results[$key] = array_map(fn($row) => $this->definition->columnDataToFieldData($key, $row),  $siteStmt->fetchAll());
			} else {
				$siteStmt = $this->buildQuery(<<<SQL
					SELECT s.* FROM %s s
				SQL, $key);
				$siteStmt->execute();
				$results[$key] = array_map(fn($row) => $this->definition->columnDataToFieldData($key, $row),  $siteStmt->fetchAll());
			}
		}

		return $results;
	}

	public function loadAllKeyNodes($key) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$reflexive = $self['reflexive'];

		$siteStmt = $this->buildQuery(<<<SQL
			SELECT * FROM %s
		SQL, $key);
		$siteStmt->execute();
		$result = $siteStmt->fetchAll();

		return $result;
	}

	public function loadRootKeyNodes($key) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$reflexive = $self['reflexive'];

		if($parent) {
			throw new \Exception("not at root");
		} else {
			if($reflexive) {
				$siteStmt = $this->buildQuery(<<<SQL
					SELECT s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent IS NULL
				SQL, $key, $key);
				$siteStmt->execute();
				$result = array_map(fn($row) => $this->definition->columnDataToFieldData($key, $row),  $siteStmt->fetchAll());
			} else {
				$siteStmt = $this->buildQuery(<<<SQL
					SELECT "/", * FROM %s
				SQL, $key);
				$siteStmt->execute();
				$result = array_map(fn($row) => $this->definition->columnDataToFieldData($key, $row),  $siteStmt->fetchAll());
			}
		}

		return $result;
	}

	public function loadChildKeyNodes($key, $parentId, $childKey) {
		$self = $this->definition->structure[$childKey];
		$parent = $self['parent'];
		$reflexive = $self['reflexive'];

		if($parent) {
			if($reflexive) {
				$routeStmt = $this->buildQuery(<<<SQL
					SELECT s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent IS NULL AND h.%s_id = :id
				SQL, $childKey, $childKey, $parent);
				$routeStmt->bindValue('id', $parentId);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll();
			} else {
				$routeStmt = $this->buildQuery(<<<SQL
					SELECT * FROM %s WHERE %s_id = :id
				SQL, $childKey, $parent);
				$routeStmt->bindValue('id', $parentId);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll();
			}
		} else {
			if($key === $childKey && $reflexive) {
				$routeStmt = $this->buildQuery(<<<SQL
					SELECT s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent = :id
				SQL, $childKey, $childKey, $childKey);
				$routeStmt->bindValue('id', $parentId);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll();
			} else {
				throw new \Exception("not at root");
			}
		}

		return array_map(fn($row) => $this->definition->columnDataToFieldData($childKey, $row), $result);
	}

	public function loadNodeSelf($key, $id) {
		$self = $this->definition->structure[$key];

		if(!$self) {
			throw new ConsistencyException("not found");
		}

		if($self['parent']) {
			$selfStmt = $this->buildQuery(<<<SQL
				SELECT *, %s_id AS _scope FROM %s WHERE id = :selfId
			SQL, $self['parent'], $key);
			$selfStmt->bindValue('selfId', $id);
			$selfStmt->execute();
			$selfData = $selfStmt->fetch();
		} else {
			$selfStmt = $this->buildQuery(<<<SQL
				SELECT *, NULL AS _scope FROM %s WHERE id = :selfId
			SQL, $key);
			$selfStmt->bindValue('selfId', $id);
			$selfStmt->execute();
			$selfData = $selfStmt->fetch();
		}


		if(!$selfData) {
			throw new ConsistencyException("not found");
		}

		return $selfData;
	}

	public function loadNodeChildren($key, $id) {
		$self = $this->definition->structure[$key];

		$children = [];

		if($self['reflexive']) {
			$childStmt = $this->buildQuery(<<<SQL
				SELECT s.*, h.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent = :parentId
		SQL, $key, $key);
			$childStmt->bindValue('parentId', $id);
			$childStmt->execute();
			$children[$key] = $childStmt->fetchAll();
		}

		foreach ($this->definition->structure as $ck => $child) {
			if($child['parent'] == $key) {
				if($child['reflexive']) {
					$childStmt = $this->buildQuery(<<<SQL
					SELECT s.*, h.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE parent IS NULL AND h.%s_id = :parentId
		SQL, $ck, $ck, $key);
					$childStmt->bindValue('parentId', $id);
					$childStmt->execute();
					$children[$ck] = $childStmt->fetchAll();
				} else {
					$childStmt = $this->buildQuery(<<<SQL
						SELECT s.* FROM %s s WHERE s.%s_id = :parentId
		SQL, $ck, $key);
					$childStmt->bindValue('parentId', $id);
					$childStmt->execute();
					$children[$ck] = $childStmt->fetchAll();
				}
			}
		}

		return $children;
	}

	public function loadNodeParents($key, $id, $limit = NULL) {
		$self = $this->definition->structure[$key];

		$totalCount = 0;

		$selfStmt = $this->buildQuery(<<<SQL
		SELECT * FROM %s WHERE id = :selfId
		SQL, $key);
		$selfStmt->bindValue('selfId', $id);
		$selfStmt->execute();
		$selfData = $selfStmt->fetch();

		$parents = [];

		if($self['reflexive']) {
			$selfReflexiveStmt = $this->buildQuery(<<<SQL
				SELECT d.* FROM %s_closure closure INNER JOIN %s d ON d.id = closure.parent_id WHERE closure.child_id = :parentId AND closure.parent_id <> closure.child_id ORDER BY closure.depth DESC
		SQL, $key, $key);
			$selfReflexiveStmt->bindValue('parentId', $id);
			$selfReflexiveStmt->execute();
			$relexiveParents = $selfReflexiveStmt->fetchAll();

			$parents[] = ['type' => $key, 'items' => $relexiveParents];

			$totalCount += count($relexiveParents);
		}

		$topPath = $this->pathToTop($key, false);

		$topId = $selfData[sprintf('%s_id', $self['parent'])] ?? NULL;
		foreach ($topPath as $pathKey) {
			if($limit !== NULL && $totalCount >= $limit) {
				break;
			}

			$parent = $this->definition->structure[$pathKey];

			if($parent['reflexive']) {
				if($parent['parent']) {
					$reflexiveStmt = $this->buildQuery(<<<SQL
					SELECT closure.%s_id AS parent, d.* FROM %s_closure closure INNER JOIN %s d ON d.id = closure.parent_id WHERE closure.child_id = :parentId ORDER BY closure.depth DESC
		SQL, $parent['parent'], $pathKey, $pathKey);
						$reflexiveStmt->bindValue('parentId', $topId);
						$reflexiveStmt->execute();
						$relexiveParents = $reflexiveStmt->fetchAll();
						$parents[] = ['type' => $pathKey, 'items' => $relexiveParents];
						$topId = $relexiveParents[0]['parent']??NULL;
						$totalCount += count($relexiveParents);

				} else {
					$reflexiveStmt = $this->buildQuery(<<<SQL
						SELECT d.* FROM %s_closure closure INNER JOIN %s d ON d.id = closure.parent_id WHERE closure.child_id = :parentId ORDER BY closure.depth DESC
		SQL, $pathKey, $pathKey);
					$reflexiveStmt->bindValue('parentId', $topId);
					$reflexiveStmt->execute();
					$relexiveParents = $reflexiveStmt->fetchAll();
					$parents[] = ['type' => $pathKey, 'items' => $relexiveParents];
					$topId = NULL;
					$totalCount += count($relexiveParents);
				}
				
			} else {
				$realParentStmt = $this->buildQuery(<<<SQL
				SELECT * FROM %s WHERE id=:parentId
		SQL, $pathKey);
				$realParentStmt->bindValue('parentId', $topId);
				$realParentStmt->execute();
				$realParent = $realParentStmt->fetch();
				$parents[] = ['type' => $pathKey, 'items' => [$realParent]];
				$topId = $realParent[sprintf('%s_id', $parent['parent'])]??NULL;
				$totalCount += 1;

			}

		}

		return array_reverse($parents);
	}

	public function loadNodesDirectParent($key, $id) {
		$self = $this->definition->structure[$key];

		if($self['reflexive']) {
			$selfReflexiveStmt = $this->buildQuery(<<<SQL
				SELECT d.parent_id FROM %s_closure d WHERE d.child_id = :id AND d.parent_id <> d.child_id ORDER BY d.depth ASC LIMIT 1
		SQL, $key);
			$selfReflexiveStmt->bindValue('id', $id);
			$selfReflexiveStmt->execute();
			$reflexId = $selfReflexiveStmt->fetchColumn();

			if($reflexId) {
				return ['key' => $key, 'id' => $reflexId];
			}
		}

		if($self['parent']) {
			$realParentStmt = $this->buildQuery(<<<SQL
			SELECT p.id FROM %s p INNER JOIN %s s ON s.%s_id = p.id WHERE s.id=:parentId
		SQL, $self['parent'], $key, $self['parent']);
			$realParentStmt->bindValue('parentId', $id);
			$realParentStmt->execute();
			$parentId = $realParentStmt->fetchColumn();
			if($parentId) {
				return ['key' => $self['parent'], 'id' => $parentId];
			}
		} else {
			return null;
		}
	}

	public function loadNode($key, $id) {
		$self = $this->loadNodeSelf($key, $id);
		$parents = $this->loadNodeParents($key, $id);
		$children = $this->loadNodeChildren($key, $id);

		$selfFields = $this->definition->columnDataToFieldData($key, $self);

		$parentsFields = [];
		foreach ($parents as $p) {
			$parentsFields[] = ['type' => $p['type'], 'items' => array_map(fn($i) => $this->definition->columnDataToFieldData($p['type'], $i), $p['items'])];
		}

		$childrenFields = [];
		foreach ($children as $type => $rows) {
			$childrenFields[$type] = array_map(fn($c) => $this->definition->columnDataToFieldData($type, $c), $rows);
		}

		return [
			'key' => $key,
			'scope' => $this->definition->structure[$key]['parent'],
			'self' => $selfFields,
			'parents' => $parentsFields,
			'children' => $childrenFields,
		];
	}

	public function loadNodeField($key, $id, $field) {
		if(!$this->definition->hasField($key, $field)) {
			throw new \Exception("invalid field");
		}
		$selfStmt = $this->buildQuery(<<<SQL
			SELECT %s FROM %s WHERE id = :selfId
		SQL, implode(', ', $this->definition->getFieldColumns($key, $field)), $key);
		$selfStmt->bindValue('selfId', $id);
		$selfStmt->execute();
		
		return $this->definition->columnDataToSingleFieldData($key, $field, $selfStmt->fetch());
	}

	public function deleteNode($key, $id) {
		$this->db->beginTransaction();

		try {
			if($this->definition->structure[$key]['reflexive']) {
				$stmt = $this->buildQuery(<<<SQL
					SELECT id FROM %s_hierarchy WHERE parent=:id
				SQL, $key);
				$stmt->bindValue('id', $id);
				$stmt->execute();
				$cids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

				$delStmt = $this->buildQuery(<<<SQL
					DELETE FROM %s WHERE id=:id
				SQL, $key);
				$delStmt2 = $this->buildQuery(<<<SQL
					DELETE FROM %s_closure WHERE child_id=:id
				SQL, $key);
				foreach($cids AS $cid) {
					$this->deleteNodeChildren($key, $cid);
				}

				foreach($cids AS $cid) {
					$delStmt->bindValue('id', $cid);
					$delStmt->execute();
					$delStmt2->bindValue('id', $cid);
					$delStmt2->execute();
				}

			}

			$this->deleteNodeChildren($key, $id);

			$delStmt = $this->buildQuery(<<<SQL
				DELETE FROM %s WHERE id=:id
			SQL, $key);
			
			$delStmt->bindValue('id', $id);
			$delStmt->execute();

			if($this->definition->structure[$key]['reflexive']) {
				$delStmt2 = $this->buildQuery(<<<SQL
					DELETE FROM %s_closure WHERE child_id=:id
				SQL, $key);
				$delStmt2->bindValue('id', $id);
				$delStmt2->execute();
			}

			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollback();
		}
	}

	private function deleteNodeChildren($key, $id) {
		if($this->definition->structure[$key]['reflexive']) {
			$stmt = $this->buildQuery(<<<SQL
				SELECT id FROM %s_hierarchy WHERE parent=:id
			SQL, $key);
			$stmt->bindValue('id', $id);
			$stmt->execute();
			$cids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

			foreach($cids AS $cid) {
				$this->deleteNodeChildren($key, $cid);
			}

			$delStmt = $this->buildQuery(<<<SQL
				DELETE FROM %s WHERE id=:id
			SQL, $key);
			$delStmt2 = $this->buildQuery(<<<SQL
				DELETE FROM %s_closure WHERE child_id=:id
			SQL, $key);

			foreach($cids AS $cid) {
				$delStmt->bindValue('id', $cid);
				$delStmt->execute();
				$delStmt2->bindValue('id', $cid);
				$delStmt2->execute();
			}
		}

		foreach ($this->definition->structure as $ck => $parent) {
			if($parent['parent'] === $key) {
				$stmt = $this->buildQuery(<<<SQL
					SELECT id FROM %s WHERE %s_id=:id
				SQL, $ck, $key);
				$stmt->bindValue('id', $id);
				$stmt->execute();
				$cids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

				foreach($cids AS $cid) {
					$this->deleteNodeChildren($ck, $cid);
				}

				if($parent['reflexive']) {
					$stmt = $this->buildQuery(<<<SQL
						DELETE FROM %s_closure WHERE %s_id=:id
					SQL, $ck, $key);
					$stmt->bindValue('id', $id);
					$stmt->execute();
				}

				$stmt = $this->buildQuery(<<<SQL
					DELETE FROM %s WHERE %s_id=:id
				SQL, $ck, $key);
				$stmt->bindValue('id', $id);
				$stmt->execute();
			}
		}
	}

	public function updateNode($key, $id, $fieldData) {
		$columnValues = $this->definition->fieldDataToColumnData($key, $fieldData);

		$stmt = $this->buildQuery(<<<SQL
			UPDATE %s SET %s WHERE id=:id
		SQL, $key, implode(',', array_map(function($col) {
			return sprintf('%s = :%s', $col, $col);
		}, array_keys($columnValues))));

		foreach ($columnValues as $k => $v) {
			$stmt->bindValue($k, $v);
		}

		$stmt->bindValue('id', $id);
		$stmt->execute();
	}

	public function moveNode($key, $id, $scopeId = NULL, $parentId = NULL) {

		$reflexive = $this->definition->structure[$key]['reflexive'];
		$parent = $this->definition->structure[$key]['parent'];


		if(($parent===NULL) !== empty($scopeId)) {
			throw new ConsistencyException("missing parent");
		}

		if(($reflexive===FALSE) && !empty($parentId)) {
			throw new ConsistencyException($parentId);
		}

		if(!empty($scopeId) && !empty($parentId)) {
			$validPositionStmt = $this->buildQuery(<<<SQL
				SELECT 1 FROM %s WHERE %s_id = :scope AND id = :id
			SQL, $key, $parent);
			$validPositionStmt->bindValue('scope', $scopeId);
			$validPositionStmt->bindValue('id', $parentId);
			$validPositionStmt->execute();

			if(!$validPositionStmt->fetchColumn()) {
				throw new ConsistencyException("invalid position");
			}
		}

		if($reflexive && !empty($parentId)) {
			$checkCycleStmt = $this->buildQuery("SELECT child_id FROM %s_closure WHERE parent_id = :id AND child_id=:newParent", $key);
			$checkCycleStmt->bindValue('id', $id);
			$checkCycleStmt->bindValue('newParent', $parentId);
			$checkCycleStmt->execute();

			if($checkCycleStmt->fetchColumn()) {
				throw new ConsistencyException("invalid position");
			}
		}

		if($reflexive && empty($parentId)) {
			$parentId = $id;
		}

		try {
			$this->db->beginTransaction();
			if($parent) {
				$stmt = $this->buildQuery(<<<SQL
					UPDATE %s SET %s_id = :parentId WHERE id = :id
				SQL, $key, $parent);
				$stmt->bindValue('id', $id);
				$stmt->bindValue('parentId', $scopeId);
				$stmt->execute();

				if($reflexive) {
					// update scope of all trans children
					// UPDATE %s SET %s_id = :parentId WHERE id IN (SELECT child_id FROM %s_closure WHERE parent_id = :id)
					$othersStmt = $this->buildQuery(<<<SQL
						UPDATE %s SET %s_id = :parentId WHERE id IN (SELECT child_id FROM %s_closure WHERE parent_id = :id)
					SQL, $key, $parent, $key);
					$othersStmt->bindValue('id', $id);
					$othersStmt->bindValue('parentId', $scopeId);
					$othersStmt->execute();

					// update scope trans closure
					// UPDATE %s_closure SET %s_id = :parentId WHERE parent_id = :id
					$closureStmt = $this->buildQuery(<<<SQL
						UPDATE %s_closure SET %s_id = :parentId WHERE child_id IN (SELECT child_id FROM %s_closure WHERE parent_id = :id)
					SQL, $key, $parent, $key);
					$closureStmt->bindValue('id', $id);
					$closureStmt->bindValue('parentId', $scopeId);
					$closureStmt->execute();
				}
			}

			if($reflexive) {
				// delete old parents: DELETE FROM %s_closure WHERE child_id = :id AND depth > 0;
				// update closure edges
				$delStmt = $this->buildQuery(<<<SQL
					DELETE FROM %s_closure WHERE child_id = :id AND depth > 0
				SQL, $key);
				$delStmt->bindValue('id', $id);
				$delStmt->execute();

				if($parent) {
					$insStmt = $this->buildQuery(<<<SQL
						INSERT INTO %s_closure (%s_id, parent_id, child_id, depth) VALUES(:scope, :parent, :child, :depth)
					SQL, $key, $parent);
					$insStmt->bindValue('scope', $scopeId);
					$insStmt->bindValue('parent', $parentId);
					$insStmt->bindValue('child', $id);
					$insStmt->bindValue('depth', 1);
					$insStmt->execute();
				} else {
					$insStmt = $this->buildQuery(<<<SQL
						INSERT INTO %s_closure (parent_id, child_id, depth) VALUES(:parent, :child, :depth)
					SQL, $key);
					$insStmt->bindValue('parent', $parentId);
					$insStmt->bindValue('child', $id);
					$insStmt->bindValue('depth', 1);
					$insStmt->execute();
				}

				$this->repairKeyClosureDefects($key, 5);
			}
			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	public function normalizeRowOrder($key, $scopeId = NULL, $parentId = NULL) {
		$self = $this->definition->structure[$key];
		$reflexive = $self['reflexive'];
		$parent = $self['parent'];
		$order = $self['order'];

		if(!$order) {
			throw new ConsistencyException("no order");
		}

		if(($parent===NULL) !== empty($scopeId)) {
			throw new ConsistencyException("missing parent");
		}

		if(($reflexive===FALSE) && !empty($parentId)) {
			throw new ConsistencyException($parentId);
		}

		if($parent) {
			if($reflexive) {
				$stmt = $this->buildQuery(<<<SQL
					UPDATE %s AS outer
					SET "%s" = normalized_order FROM 
					(SELECT id AS inner_id, normalized_order FROM %s_normalized_order WHERE stored_order <> normalized_order AND scope=:scope AND parent IS :parent) 
					WHERE outer.id = inner_id
				SQL, 
					$key, $order, $key
				);
				$stmt->bindValue('parent', $parentId);
				$stmt->bindValue('scope', $scopeId);
				$stmt->execute();
			} else {
				$stmt = $this->buildQuery(<<<SQL
					UPDATE %s AS outer
					SET "%s" = normalized_order FROM 
					(SELECT id AS inner_id, normalized_order FROM %s_normalized_order WHERE stored_order <> normalized_order AND scope=:scope) 
					WHERE outer.id = inner_id
				SQL, 
					$key, $order, $key
				);
				$stmt->bindValue('scope', $scopeId);
				$stmt->execute();
			}
		} else {
			if($reflexive) {
				$stmt = $this->buildQuery(<<<SQL
					UPDATE %s AS outer
					SET "%s" = normalized_order FROM 
					(SELECT id AS inner_id, normalized_order FROM %s_normalized_order WHERE stored_order <> normalized_order AND parent IS :parent) 
					WHERE outer.id = inner_id
				SQL, 
					$key, $order, $key
				);
				$stmt->bindValue('parent', $parentId);
				$stmt->execute();
			} else {
				$stmt = $this->buildQuery(<<<SQL
					UPDATE %s AS outer
					SET "%s" = normalized_order FROM 
					(SELECT id AS inner_id, normalized_order FROM %s_normalized_order WHERE stored_order <> normalized_order) 
					WHERE outer.id = inner_id
				SQL, 
					$key, $order, $key
				);
				$stmt->execute();
			}
		}
	}

	public function normalizedAllRowOrder() {
		foreach ($this->definition->structure as $key => $conf) {
			if($conf['order']) {
				$this->normalizedKeyAllRowOrder($key);
			}
		}
	}

	public function normalizedKeyAllRowOrder($key) {
		$self = $this->definition->structure[$key];
		$reflexive = $self['reflexive'];
		$parent = $self['parent'];
		$order = $self['order'];

		if(!$order) {
			throw new ConsistencyException("no order");
		}

		$stmt = $this->buildQuery(<<<SQL
			UPDATE %s AS outer
			SET "%s" = normalized_order FROM 
			(SELECT id AS inner_id, normalized_order FROM %s_normalized_order WHERE stored_order <> normalized_order) 
			WHERE outer.id = inner_id
		SQL, 
			$key, $order, $key
		);
		$stmt->execute();
	}

	public function loadAllRowOrder() {
		foreach ($this->definition->structure as $key => $conf) {
			if(!$conf['order']) {
				continue;
			}

			$result[$key] = $this->loadKeyRowOrder($key);
		}

		return $result;
	}

	public function loadKeyRowOrder($key) {
		$self = $this->definition->structure[$key];
		$reflexive = $self['reflexive'];
		$parent = $self['parent'];
		$order = $self['order'];

		if(!$order) {
			throw new ConsistencyException("no order");
		}

		$stmt = $this->buildQuery(<<<SQL
		SELECT * FROM %s_normalized_order WHERE stored_order <> normalized_order
		SQL, $key);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function orderNodeUp($key, $id) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$order = $self['order'];


		$this->db->beginTransaction();

		$stmt = $this->buildQuery(<<<SQL
			SELECT CASE WHEN COUNT(DISTINCT equal.id) THEN MIN(equal.priority) + 1 ELSE MIN(greater.priority) END AS next
			FROM %s_hierarchy self 
			LEFT JOIN %s_hierarchy greater 
			ON (self.%s_id,self.parent) IS (greater.%s_id,greater.parent) 
			AND greater.priority > self.priority 
			AND self.id <> greater.id
			LEFT JOIN %s_hierarchy equal 
			ON (self.%s_id,self.parent) IS (equal.%s_id,equal.parent) 
			AND equal.priority = self.priority 
			AND self.id <> equal.id
			WHERE self.id = :id
			GROUP BY self.id
		SQL, 
			$key, $key, $parent, $parent, $key, $parent, $parent
		);
		$stmt->bindValue('id', $id);
		$stmt->execute();

		$newPos = $stmt->fetchColumn();

		if($newPos !== null) {
			$updateStmt = $this->buildQuery(<<<SQL
				UPDATE %s SET %s=:new WHERE id=:id
			SQL, $key, $order);
			$updateStmt->bindValue('id', $id);
			$updateStmt->bindValue('new', $newPos);
			$updateStmt->execute();
		}


		$this->db->commit();
	}

	public function orderNodeToTop($key, $id) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$order = $self['order'];


		$this->db->beginTransaction();

		$stmt = $this->buildQuery(<<<SQL
			SELECT max(greater.priority)+1 next
			FROM %s_hierarchy self 
			LEFT JOIN %s_hierarchy greater 
			ON (self.%s_id, self.parent) IS (greater.%s_id, greater.parent) 
			AND greater.priority > self.priority 
			AND self.id <> greater.id
			WHERE self.id = :id
			GROUP BY self.id
		SQL, 
			$key, $key, $parent, $parent
		);
		$stmt->bindValue('id', $id);
		$stmt->execute();

		$newPos = $stmt->fetchColumn();

		if($newPos !== null) {
			$updateStmt = $this->buildQuery(<<<SQL
				UPDATE %s SET %s=:new WHERE id=:id
			SQL, $key, $order);
			$updateStmt->bindValue('id', $id);
			$updateStmt->bindValue('new', $newPos);
			$updateStmt->execute();
		}


		$this->db->commit();
	}

	public function orderNodeDown($key, $id) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$order = $self['order'];


		$this->db->beginTransaction();

		$stmt = $this->buildQuery(<<<SQL
			SELECT CASE WHEN COUNT(DISTINCT equal.id) THEN MAX(equal.priority) - 1 ELSE MAX(lesser.priority) END AS next
			FROM %s_hierarchy self 
			LEFT JOIN %s_hierarchy lesser 
			ON (self.%s_id,self.parent) IS (lesser.%s_id,lesser.parent) 
			AND lesser.priority < self.priority 
			AND self.id <> lesser.id
			LEFT JOIN %s_hierarchy equal 
			ON (self.%s_id,self.parent) IS (equal.%s_id,equal.parent) 
			AND equal.priority = self.priority 
			AND self.id <> equal.id
			WHERE self.id = :id
			GROUP BY self.id
		SQL, 
			$key, $key, $parent, $parent, $key, $parent, $parent
		);
		$stmt->bindValue('id', $id);
		$stmt->execute();

		$newPos = $stmt->fetchColumn();

		if($newPos !== null) {
			$updateStmt = $this->buildQuery(<<<SQL
				UPDATE %s SET %s=:new WHERE id=:id
			SQL, $key, $order);
			$updateStmt->bindValue('id', $id);
			$updateStmt->bindValue('new', $newPos);
			$updateStmt->execute();
		}

		$this->db->commit();
	}

	public function orderNodeToBottom($key, $id) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$order = $self['order'];


		$this->db->beginTransaction();

		$stmt = $this->buildQuery(<<<SQL
			SELECT MIN(lesser.priority)-1 AS next
			FROM %s_hierarchy self 
			LEFT JOIN %s_hierarchy lesser 
			ON (self.%s_id,self.parent) IS (lesser.%s_id,lesser.parent) 
			AND lesser.priority < self.priority 
			AND self.id <> lesser.id
			WHERE self.id = :id
			GROUP BY self.id
		SQL, 
			$key, $key, $parent, $parent
		);
		$stmt->bindValue('id', $id);
		$stmt->execute();

		$newPos = $stmt->fetchColumn();

		if($newPos !== null) {
			$updateStmt = $this->buildQuery(<<<SQL
				UPDATE %s SET %s=:new WHERE id=:id
			SQL, $key, $order);
			$updateStmt->bindValue('id', $id);
			$updateStmt->bindValue('new', $newPos);
			$updateStmt->execute();
		}

		$this->db->commit();
	}

	public function createNode($key, $fieldData, $scopeId = NULL, $parentId = NULL) {
		$reflexive = $this->definition->structure[$key]['reflexive'];
		$parent = $this->definition->structure[$key]['parent'];
		$order = $this->definition->structure[$key]['order'];


		if(($parent===NULL) !== empty($scopeId)) {
			throw new ConsistencyException("parent");
		}

		if(($reflexive===FALSE) && !empty($parentId)) {
			throw new ConsistencyException($parentId);
		}

		$columnValues = $this->definition->fieldDataToColumnData($key, $fieldData);
		$columnNames = array_keys($columnValues);
		$columnNameString = implode(',', $columnNames);
		$columnParamString = ':' . implode(', :', $columnNames);

		if(!empty($scopeId) && !empty($parentId)) {
			$validPositionStmt = $this->buildQuery(<<<SQL
				SELECT 1 FROM %s WHERE %s_id = :scope AND id = :id
			SQL, $key, $parent);
			$validPositionStmt->bindValue('scope', $scopeId);
			$validPositionStmt->bindValue('id', $parentId);
			$validPositionStmt->execute();

			if(!$validPositionStmt->fetchColumn()) {
				throw new ConsistencyException("invalid position");
			}
		}


		try {
			$this->db->beginTransaction();
			if($parent) {
				$stmt = $this->buildQuery(<<<SQL
					INSERT INTO %s (%s_id, %s) VALUES (:parentId, %s)
				SQL, $key, $parent, $columnNameString, $columnParamString);
				$stmt->bindValue('parentId', $scopeId);
				foreach ($columnValues as $k => $v) {
					$stmt->bindValue($k, $v);
				}
				$stmt->execute();

				$lastId = $this->db->lastInsertId();
				if($reflexive) {
					$stmt = $this->buildQuery(<<<SQL
						INSERT INTO %s_closure (%s_id, parent_id, child_id, depth) VALUES (:scope, :parent,:child,:depth)
					SQL, $key, $parent);
					$stmt->bindValue('scope', $scopeId);
					$stmt->bindValue('parent', $lastId);
					$stmt->bindValue('child', $lastId);
					$stmt->bindValue('depth', 0);
					$stmt->execute();
					if(!empty($parentId)) {
						$stmt->bindValue('scope', $scopeId);
						$stmt->bindValue('parent', $parentId);
						$stmt->bindValue('child', $lastId);
						$stmt->bindValue('depth', 1);
						$stmt->execute();
					}
				}
			} else {
				$stmt = $this->buildQuery(<<<SQL
					INSERT INTO %s (%s) VALUES (%s)
				SQL, $key, $columnNameString, $columnParamString);
				foreach ($columnValues as $k => $v) {
					$stmt->bindValue($k, $v);
				}
				$stmt->execute();
				$lastId = $this->db->lastInsertId();
				if($reflexive) {
					$stmt = $this->buildQuery(<<<SQL
						INSERT INTO %s_closure (parent_id, child_id, depth) VALUES (:parent,:child,:depth)
					SQL, $key);
					$stmt->bindValue('parent', $lastId);
					$stmt->bindValue('child', $lastId);
					$stmt->bindValue('depth', 0);
					$stmt->execute();
					if(!empty($parentId)) {
						$stmt->bindValue('parent', $parentId);
						$stmt->bindValue('child', $lastId);
						$stmt->bindValue('depth', 1);
						$stmt->execute();
					}
				}
			}
			$this->repairKeyClosureDefects($key, 5);

			if($order) {
				$this->normalizeRowOrder($key, $scopeId?:null, $parentId?:null);
			}

			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollback();
			throw $e;
		}

		return $lastId;
	}

	public function pathToTop($key, $self = true) {
		$currentKey = $key;

		$result = [];

		while($this->definition->structure[$currentKey] ?? false) {
			$result[] = $currentKey;
			$currentKey = $this->definition->structure[$currentKey]['parent'];
		}

		if(!$self) {
			array_shift($result);
		}

		return $result;
	}

	private function tableSQL($table, $definition) {
		$parent = $definition['parent'];
		$parentSingle = $definition['parent_single'];
		$reflexive = $definition['reflexive'];
		$order = $definition['order'];
		$columns = $this->definition->getColumns($table);

		$columnSql = implode("\n", array_map(function($column) {
			return sprintf('"%s"	TEXT NOT NULL,', $column);
		}, $columns));

		if($order) {
			$columnSql .= sprintf('"%s"	INTEGER NOT NULL DEFAULT 0,', $order);
		}

		if($parent) {
			if($parentSingle) {
				$uniqSql = str_replace('%PARENT%',$parent,'UNIQUE("%PARENT%_id")');
			} else {
				$uniqSql = str_replace('%PARENT%',$parent,'UNIQUE("id","%PARENT%_id")');
			}

			return str_replace(['%NAME%', '%PARENT%', '%COLUMNS%', '%UNIQ%'],[$table, $parent, $columnSql, $uniqSql], <<<SQL
				CREATE TABLE IF NOT EXISTS "%NAME%" (
					"id"	INTEGER NOT NULL,
					%COLUMNS%
					"%PARENT%_id"	INTEGER NOT NULL,
					FOREIGN KEY("%PARENT%_id") REFERENCES "%PARENT%"("id") ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
					PRIMARY KEY("id" AUTOINCREMENT),
					%UNIQ%
				);
			SQL);
		} else {
			return str_replace(['%NAME%','%COLUMNS%'],[$table, $columnSql], <<<SQL
				CREATE TABLE IF NOT EXISTS "%NAME%" (
					"id"	INTEGER NOT NULL,
					%COLUMNS%
					PRIMARY KEY("id" AUTOINCREMENT)
				);
			SQL);
		}
	}

	private function closureSQL($table, $scope = NULL) {

		if($scope) {
			return str_replace(['%NAME%', '%SCOPE%'], [$table, $scope], <<<SQL
				CREATE TABLE "%NAME%_closure" (
					"id"	INTEGER NOT NULL,
					"%SCOPE%_id"	INTEGER NOT NULL,
					"parent_id"	INTEGER NOT NULL,
					"child_id"	INTEGER NOT NULL,
					"depth"	INTEGER NOT NULL,
					PRIMARY KEY("id" AUTOINCREMENT),
					FOREIGN KEY("%SCOPE%_id") REFERENCES "%SCOPE%" ("id") ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
					FOREIGN KEY("parent_id","%SCOPE%_id") REFERENCES "%NAME%" ("id","%SCOPE%_id") ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
					FOREIGN KEY("child_id","%SCOPE%_id") REFERENCES "%NAME%" ("id","%SCOPE%_id") ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED
				);
			SQL);
		} else {
			return str_replace(['%NAME%', '%SCOPE%'], [$table, $scope], <<<SQL
				CREATE TABLE "%NAME%_closure" (
					"id"	INTEGER NOT NULL,
					"parent_id"	INTEGER NOT NULL,
					"child_id"	INTEGER NOT NULL,
					"depth"	INTEGER NOT NULL,
					PRIMARY KEY("id" AUTOINCREMENT),
					FOREIGN KEY("parent_id") REFERENCES "%NAME%" ("id") ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
					FOREIGN KEY("child_id") REFERENCES "%NAME%" ("id") ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED
				);
			SQL);
		}
	}

	private function normalizedOrderSQL($table, $order, $parent = NULL, $reflexive = false) {
		if($parent) {
			if($reflexive) {
				return str_replace(['%NAME%', '%ORDER%', '%SCOPE%'], [$table, $order, $parent], <<<SQL
					SELECT 
					s.id AS id,
					s.'%ORDER%' AS stored_order,
					h.parent AS parent,
					h.%SCOPE%_id AS scope,
					ROW_NUMBER() OVER(PARTITION BY h.parent, h.%SCOPE%_id ORDER BY h.%ORDER% ASC, h.id DESC) AS normalized_order FROM %NAME%_hierarchy h INNER JOIN %NAME% s ON s.id=h.id
					SQL);
			} else {
				return str_replace(['%NAME%', '%ORDER%', '%SCOPE%'], [$table, $order, $parent], <<<SQL
					SELECT 
						s.id AS id,
						s.'%ORDER%' AS stored_order,
						s.'%SCOPE%_id' AS scope,
						ROW_NUMBER() OVER(PARTITION BY %SCOPE%_id ORDER BY '%ORDER%' ASC, id DESC) AS normalized_order FROM %NAME% s
					SQL);
			}
		} else {
			if($reflexive) {
				return str_replace(['%NAME%', '%ORDER%', '%SCOPE%'], [$table, $order, $parent], <<<SQL
					SELECT 
						s.id AS id,
						s.'%ORDER%' AS stored_order,
						h.parent AS parent,
						ROW_NUMBER() OVER(PARTITION BY parent ORDER BY s.'%ORDER%' ASC, s.id DESC) AS normalized_order FROM %NAME%_hierarchy h INNER JOIN %NAME% s ON h.id=s.id
					SQL);
			} else {
				return str_replace(['%NAME%', '%ORDER%', '%SCOPE%'], [$table, $order, $parent], <<<SQL
					SELECT 
						s.id AS id,
						s.'%ORDER%' AS stored_order,
						ROW_NUMBER() OVER(ORDER BY '%ORDER%' ASC, id DESC) AS normalized_order FROM %NAME% s
					SQL);
			}
		}
	}

	private function hierarchySQL($table, $scope = NULL, $order = NULL) {
		$sqlOrderSelect = $order ? sprintf("\n self.%s AS %s,", $order, $order) : '';
		$sqlOrderBy = $order ? sprintf("\n self.%s DESC,", $order) : '';

		if($scope) {
			return str_replace(['%NAME%', '%SCOPE%', '%ORDER_SELECT%', '%ORDER_BY%'], [$table, $scope, $sqlOrderSelect, $sqlOrderBy], <<<SQL
				SELECT
					scope.id AS %SCOPE%_id,
					parent.id AS parent, %ORDER_SELECT%
					self.id AS id
				FROM
					%NAME% self
					INNER JOIN %SCOPE% scope
					ON scope.id = self.%SCOPE%_id
					INNER JOIN %NAME%_closure reflexive
					ON reflexive.depth = 0
					AND reflexive.child_id = self.id
					AND reflexive.parent_id = self.id
					LEFT JOIN %NAME%_closure closure
					ON closure.child_id = self.id AND closure.depth = 1
					LEFT JOIN %NAME% parent
					ON parent.id = closure.parent_id
				ORDER BY
				    parent.id ASC, %ORDER_BY%
					self.id ASC
			SQL);
		} else {
			return str_replace(['%NAME%', '%ORDER_SELECT%', '%ORDER_BY%'], [$table, $sqlOrderSelect, $sqlOrderBy], <<<SQL
				SELECT
					parent.id AS parent, %ORDER_SELECT%
					self.id AS id
				FROM
					%NAME% self
					INNER JOIN %NAME%_closure reflexive
					ON reflexive.depth = 0
					AND reflexive.child_id = self.id
					AND reflexive.parent_id = self.id
					LEFT JOIN %NAME%_closure closure
					ON closure.child_id = self.id AND closure.depth = 1
					LEFT JOIN %NAME% parent
					ON parent.id = closure.parent_id
				ORDER BY
				    parent.id ASC, %ORDER_BY%
					self.id ASC
			SQL);
		}
	}

	private function checkMissingSQL($table, $scope = NULL) {
		if($scope) {
			return str_replace(['%NAME%', '%SCOPE%'], [$table, $scope], <<<SQL
				SELECT
					NULL AS id,
					a.%SCOPE%_id AS %SCOPE%_id,
					a.parent_id AS parent_id,
					b.child_id AS child_id,
					a.depth + b.depth AS depth,
					"transitivity" AS reason
				FROM
					%NAME%_closure a,
					%NAME%_closure b
				WHERE
					a.id <> b.id
					AND
					a.%SCOPE%_id = b.%SCOPE%_id
					AND
					b.parent_id = a.child_id
					AND
					NOT EXISTS (
						SELECT id
						FROM %NAME%_closure t WHERE
						(t.parent_id, t.child_id, t.depth)
						IS (a.parent_id, b.child_id, a.depth + b.depth)
						AND
						t.%SCOPE%_id = a.%SCOPE%_id
					)
				UNION
				SELECT
					NULL AS id,
					m.%SCOPE%_id AS %SCOPE%_id,
					id AS parent_id,
					id AS child_id,
					0 AS depth,
					"reflexivity" AS reason
				FROM
					%NAME% m
				WHERE
					NOT EXISTS (
						SELECT id FROM %NAME%_closure r
						WHERE (r.parent_id, r.child_id, r.depth)
						IS (m.id, m.id, 0)
					)
			SQL);
		} else {
			return str_replace(['%NAME%'], [$table], <<<SQL
				SELECT
					NULL AS id,
					a.parent_id AS parent_id,
					b.child_id AS child_id,
					a.depth + b.depth AS depth,
					"transitivity" AS reason
				FROM
					%NAME%_closure a,
					%NAME%_closure b
				WHERE
					a.id <> b.id
					AND
					b.parent_id = a.child_id
					AND
					NOT EXISTS (
						SELECT id
						FROM %NAME%_closure t WHERE
						(t.parent_id, t.child_id, t.depth)
						IS (a.parent_id, b.child_id, a.depth + b.depth)
					)
				UNION
				SELECT
					NULL AS id,
					id AS parent_id,
					id AS child_id,
					0 AS depth,
					"reflexivity" AS reason
				FROM
					%NAME% m
				WHERE
					NOT EXISTS (
						SELECT id FROM %NAME%_closure r
						WHERE (r.parent_id, r.child_id, r.depth)
						IS (m.id, m.id, 0)
					)
			SQL);
		}
	}

	private function checkInvalidSQL($table, $scope = NULL) {
		if($scope) {
			return str_replace(['%NAME%', '%SCOPE%'], [$table, $scope], <<<SQL
				SELECT
					t.%SCOPE%_id AS %SCOPE%_id,
					t.id AS id,
					t.parent_id AS parent_id,
					t.child_id AS child_id,
					t.depth AS depth
				FROM
					%NAME%_closure t
				WHERE (t.depth = 0 AND t.child_id <> t.parent_id) 
				OR (t.depth <> 0 AND t.child_id IS t.parent_id) 
				OR (t.depth > 1 AND NOT EXISTS (
						SELECT a.id
						FROM %NAME%_closure a
						INNER JOIN %NAME%_closure b ON a.child_id = b.parent_id
						WHERE
							(a.%SCOPE%_id, b.%SCOPE%_id) IS (t.%SCOPE%_id, t.%SCOPE%_id)
							AND (a.depth + b.depth) = t.depth
							AND a.id <> t.id
							AND b.id <> t.id
							AND (t.parent_id, t.child_id)
							IS (a.parent_id, b.child_id)
				)) 
				OR (t.child_id <> t.parent_id AND EXISTS (
						SELECT r.id
						FROM %NAME%_closure r
						WHERE (r.child_id, r.parent_id) = (t.parent_id, t.child_id)
					)
				)
			SQL);
		} else {
			return str_replace(['%NAME%'], [$table], <<<SQL
				SELECT
					t.id AS id,
					t.parent_id AS parent_id,
					t.child_id AS child_id,
					t.depth AS depth
				FROM
					%NAME%_closure t
				WHERE (t.depth = 0 AND t.child_id <> t.parent_id ) 
				OR (t.depth <> 0 AND t.child_id IS t.parent_id) 
				OR (t.depth > 1 AND NOT EXISTS (
						SELECT a.id
						FROM %NAME%_closure a
						INNER JOIN %NAME%_closure b ON a.child_id = b.parent_id
						WHERE (a.depth + b.depth) = t.depth 
							AND a.id <> t.id
							AND b.id <> t.id
							AND (t.parent_id, t.child_id)
							IS (a.parent_id, b.child_id)
					)
				)
				OR (t.child_id <> t.parent_id AND EXISTS (
						SELECT r.id
						FROM %NAME%_closure r
						WHERE (r.child_id, r.parent_id) = (t.parent_id, t.child_id)
					)
				)
			SQL);
		}
	}
	
	private function buildQuery($template, ...$params) {
		$q = sprintf($template, ...$params);
		return $this->db->prepare($q);
	}
}