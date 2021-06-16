<?php

namespace App\Hierarchy;

use Doctrine\DBAL\Connection;

class Repository {
	public function __construct(public Connection $db, Definition $definition) {
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

	public function repairClosures() {
		foreach ($this->definition->structure as $key => $parent) {
			if(!$parent['reflexive']) {
				continue;
			}
			$limit = 5;
			do {
				$dirty = false;
				$checkInvalid = $this->buildQuery('DELETE FROM %s_closure WHERE id IN (SELECT id FROM %s_closure_invalid)', $key, $key);
				$checkInvalid->execute();
				$affectedInvalid = $checkInvalid->rowCount();
				if($affectedInvalid) {
					$dirty = true;
				}

				$checkMissing = $this->buildQuery('INSERT INTO %s_closure SELECT * FROM %s_closure_missing;', $key, $key);
				$checkMissing->execute();
				$affectedMissing = $checkMissing->rowCount();

				if($affectedMissing) {
					$dirty = true;
				}

				if($limit-- < 0) {
					if($trans) {
						$this->db->rollback();
					}
					throw new ConsistencyException("limit reached");
				}
			} while($dirty);
		}

	}

	public function createSchema() {
		$sorted = $this->definition->topoSorted();

		$this->db->beginTransaction();

		try {
			foreach (array_reverse($sorted) as $key) {
				$parent = $this->definition->structure[$key];

				if($parent['reflexive']) {

					$createHierarchyView = $this->buildQuery(
						sprintf("DROP VIEW IF EXISTS %s_hierarchy;", $key)
					);
					$createHierarchyView->execute();
					$createCheckMissingView = $this->buildQuery(
						sprintf("DROP VIEW IF EXISTS %s_closure_missing;",$key)
					);
					$createCheckMissingView->execute();
					$createCheckInvalidView = $this->buildQuery(
						sprintf("DROP VIEW IF EXISTS %s_closure_invalid;", $key)
					);
					$createCheckInvalidView->execute();

					$dropClosure = $this->buildQuery(
						sprintf('DROP TABLE IF EXISTS "%s_closure";', $key)
					);
					$dropClosure->execute();
				}

				if($parent['generator']) {
					$dropGenerator = $this->buildQuery(
						sprintf('DROP TABLE IF EXISTS "%s_generator";', $key)
					);
					$dropGenerator->execute();
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

				if($parent['generator']) {
					$createGenerator = $this->buildQuery(
						$this->generatorSQL($key)
					);
					$createGenerator->execute();
				}

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

			if($parent['reflexive']) {
				$sql .= sprintf("DROP VIEW IF EXISTS %s_hierarchy;", $key). PHP_EOL;
				$sql .= sprintf("DROP VIEW IF EXISTS %s_closure_missing;",$key). PHP_EOL;
				$sql .= sprintf("DROP VIEW IF EXISTS %s_closure_invalid;", $key). PHP_EOL;
				$sql .= sprintf('DROP TABLE IF EXISTS "%s_closure";', $key). PHP_EOL;
			}

			if($parent['generator']) {
				$sql .= sprintf('DROP TABLE IF EXISTS "%s_generator";', $key). PHP_EOL;
			}

			$sql .= sprintf('DROP TABLE IF EXISTS "%s";', $key). PHP_EOL;
		}

		foreach ($sorted as $key) {
			$parent = $this->definition->structure[$key];
			$sql .= $this->tableSQL($key, $parent);

			if($parent['generator']) {
				$sql .= $this->generatorSQL($key);
			}

			if($parent['reflexive']) {
				$this->closureSQL($key, $parent['parent']);

				$sql .= sprintf("CREATE VIEW IF NOT EXISTS %s_hierarchy AS %s;", $key,
				$this->hierarchySQL($key, $parent['parent'], $parent['order']));

				$sql .= sprintf("CREATE VIEW IF NOT EXISTS %s_closure_missing AS %s;", $key,
				$this->checkMissingSQL($key, $parent['parent']));

				$sql .= sprintf("CREATE VIEW IF NOT EXISTS %s_closure_invalid AS %s;", $key,
				$this->checkInvalidSQL($key, $parent['parent']));
			}
		}

		return $sql;
	}

	public function loadHierarchy() {
		$result = [];

		foreach ($this->definition->structure as $key => $parent) {
			if($parent['parent']) {
				if($parent['reflexive']) {
					$routeStmt = $this->buildQuery('SELECT (h.%s_id ||"/"||IFNULL(h.parent,"")), s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id', $parent['parent'], $key, $key);
					$routeStmt->execute();
					$result[$key] = $routeStmt->fetchAll(\PDO::FETCH_GROUP);
				} else {
					$routeStmt = $this->buildQuery('SELECT %s_id||"/", * FROM %s', $parent['parent'], $key);
					$routeStmt->execute();
					$result[$key] = $routeStmt->fetchAll(\PDO::FETCH_GROUP);
				}
			} else {
				if($parent['reflexive']) {
					$siteStmt = $this->buildQuery('SELECT "/"||IFNULL(h.parent,""), s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id', $key, $key);
					$siteStmt->execute();
					$result[$key] = $siteStmt->fetchAll(\PDO::FETCH_GROUP);
				} else {
					$siteStmt = $this->buildQuery('SELECT "/", * FROM %s', $key);
					$siteStmt->execute();
					$result[$key] = $siteStmt->fetchAll(\PDO::FETCH_GROUP);
				}
			}
		}

		return new Tree($this->definition, $result);
	}

	public function loadPartialHierarchy($key) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$reflexive = $self['reflexive'];

		if($parent) {
			if($reflexive) {
				$routeStmt = $this->buildQuery('SELECT (h.%s_id ||"/"||IFNULL(h.parent,"")), s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id', $parent, $key, $key);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll(\PDO::FETCH_GROUP);
			} else {
				$routeStmt = $this->buildQuery('SELECT %s_id||"/", * FROM %s', $parent, $key);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll(\PDO::FETCH_GROUP);
			}
		} else {
			if($reflexive) {
				$siteStmt = $this->buildQuery('SELECT "/"||IFNULL(h.parent,""), s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id', $key, $key);
				$siteStmt->execute();
				$result = $siteStmt->fetchAll(\PDO::FETCH_GROUP);
			} else {
				$siteStmt = $this->buildQuery('SELECT "/", * FROM %s', $key);
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
				$siteStmt = $this->buildQuery('SELECT s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent IS NULL', $key, $key);
				$siteStmt->execute();
				$results[$key] = $siteStmt->fetchAll();
			} else {
				$siteStmt = $this->buildQuery('SELECT s.* FROM %s s', $key);
				$siteStmt->execute();
				$results[$key] = $siteStmt->fetchAll();
			}
		}

		return $results;
	}

	public function loadKeyNodes($key) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$reflexive = $self['reflexive'];

		if($parent) {
			throw new \Exception("not at root");
		} else {
			if($reflexive) {
				$siteStmt = $this->buildQuery('SELECT s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent IS NULL', $key, $key);
				$siteStmt->execute();
				$result = $siteStmt->fetchAll();
			} else {
				$siteStmt = $this->buildQuery('SELECT "/", * FROM %s', $key);
				$siteStmt->execute();
				$result = $siteStmt->fetchAll();
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
				$routeStmt = $this->buildQuery('SELECT s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent IS NULL AND h.%s_id = :id', $childKey, $childKey, $parent);
				$routeStmt->bindValue('id', $parentId);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll();
			} else {
				$routeStmt = $this->buildQuery('SELECT * FROM %s WHERE %s_id = :id', $childKey, $parent);
				$routeStmt->bindValue('id', $parentId);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll();
			}
		} else {
			if($key === $childKey && $reflexive) {
				$routeStmt = $this->buildQuery('SELECT s.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent = :id', $childKey, $childKey, $childKey);
				$routeStmt->bindValue('id', $parentId);
				$routeStmt->execute();
				$result = $routeStmt->fetchAll();
			} else {
				throw new \Exception("not at root");
			}
		}

		return $result;
	}

	public function loadNodeSelf($key, $id) {
		$self = $this->definition->structure[$key];

		if(!$self) {
			throw new ConsistencyException("not found");
		}

		if($self['parent']) {
			$selfStmt = $this->buildQuery('SELECT *, %s_id AS _scope FROM %s WHERE id = :selfId', $self['parent'], $key);
			$selfStmt->bindValue('selfId', $id);
			$selfStmt->execute();
			$selfData = $selfStmt->fetch();
		} else {
			$selfStmt = $this->buildQuery('SELECT *, NULL AS _scope FROM %s WHERE id = :selfId', $key);
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
			$childStmt = $this->buildQuery('SELECT s.*, h.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE h.parent = :parentId', $key, $key);
			$childStmt->bindValue('parentId', $id);
			$childStmt->execute();
			$children[$key] = $childStmt->fetchAll();
		}

		foreach ($this->definition->structure as $ck => $child) {
			if($child['parent'] == $key) {
				if($child['reflexive']) {
					$childStmt = $this->buildQuery('SELECT s.*, h.* FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE parent IS NULL AND h.%s_id = :parentId', $ck, $ck, $key);
					$childStmt->bindValue('parentId', $id);
					$childStmt->execute();
					$children[$ck] = $childStmt->fetchAll();
				} else {
					$childStmt = $this->buildQuery('SELECT s.* FROM %s s WHERE s.%s_id = :parentId', $ck, $key);
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

		$selfStmt = $this->buildQuery('SELECT * FROM %s WHERE id = :selfId', $key);
		$selfStmt->bindValue('selfId', $id);
		$selfStmt->execute();
		$selfData = $selfStmt->fetch();

		$parents = [];

		if($self['reflexive']) {
			$selfReflexiveStmt = $this->buildQuery('SELECT d.* FROM %s_closure closure INNER JOIN %s d ON d.id = closure.parent_id WHERE closure.child_id = :parentId AND closure.parent_id <> closure.child_id ORDER BY closure.depth DESC', $key, $key);
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
					$reflexiveStmt = $this->buildQuery('SELECT closure.%s_id AS parent, d.* FROM %s_closure closure INNER JOIN %s d ON d.id = closure.parent_id WHERE closure.child_id = :parentId ORDER BY closure.depth DESC', $parent['parent'], $pathKey, $pathKey);
						$reflexiveStmt->bindValue('parentId', $topId);
						$reflexiveStmt->execute();
						$relexiveParents = $reflexiveStmt->fetchAll();
						$parents[] = ['type' => $pathKey, 'items' => $relexiveParents];
						$topId = $relexiveParents[0]['parent']??NULL;
						$totalCount += count($relexiveParents);
				} else {
					$reflexiveStmt = $this->buildQuery('SELECT d.* FROM %s_closure closure INNER JOIN %s d ON d.id = closure.parent_id WHERE closure.child_id = :parentId ORDER BY closure.depth DESC', $pathKey, $pathKey);
					$reflexiveStmt->bindValue('parentId', $topId);
					$reflexiveStmt->execute();
					$relexiveParents = $reflexiveStmt->fetchAll();
					$parents[] = ['type' => $pathKey, 'items' => $relexiveParents];
					$topId = NULL;
					$totalCount += count($relexiveParents);
				}
				
			} else {
				$realParentStmt = $this->buildQuery('SELECT * FROM %s WHERE id=:parentId', $pathKey);
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
			$selfReflexiveStmt = $this->buildQuery('SELECT d.parent_id FROM %s_closure d WHERE d.child_id = :id AND d.parent_id <> d.child_id ORDER BY d.depth ASC LIMIT 1', $key);
			$selfReflexiveStmt->bindValue('id', $id);
			$selfReflexiveStmt->execute();
			$reflexId = $selfReflexiveStmt->fetchColumn();
			if($reflexId) {
				return ['key' => $key, 'id' => $reflexId];
			}
		}

		if($self['parent']) {
			$realParentStmt = $this->buildQuery('SELECT p.id FROM %s p INNER JOIN %s s ON s.%s_id = p.id WHERE s.id=:parentId', $self['parent'], $key, $self['parent']);
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
		return [
			'type' => $key,
			'self' => $this->loadNodeSelf($key, $id),
			'parents' => $this->loadNodeParents($key, $id),
			'children' => $this->loadNodeChildren($key, $id),
		];
	}

	public function deleteNode($key, $id) {
		$this->db->beginTransaction();

		try {
			if($this->definition->structure[$key]['reflexive']) {
				$stmt = $this->buildQuery('SELECT id FROM %s_hierarchy WHERE parent=:id', $key);
				$stmt->bindValue('id', $id);
				$stmt->execute();
				$cids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

				$delStmt = $this->buildQuery('DELETE FROM %s WHERE id=:id', $key);
				$delStmt2 = $this->buildQuery('DELETE FROM %s_closure WHERE child_id=:id', $key);
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

			$delStmt = $this->buildQuery('DELETE FROM %s WHERE id=:id', $key);
			
			$delStmt->bindValue('id', $id);
			$delStmt->execute();

			if($this->definition->structure[$key]['reflexive']) {
				$delStmt2 = $this->buildQuery('DELETE FROM %s_closure WHERE child_id=:id', $key);
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
			$stmt = $this->buildQuery('SELECT id FROM %s_hierarchy WHERE parent=:id', $key);
			$stmt->bindValue('id', $id);
			$stmt->execute();
			$cids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

			foreach($cids AS $cid) {
				$this->deleteNodeChildren($key, $cid);
			}

			$delStmt = $this->buildQuery('DELETE FROM %s WHERE id=:id', $key);
			$delStmt2 = $this->buildQuery('DELETE FROM %s_closure WHERE child_id=:id', $key);

			foreach($cids AS $cid) {
				$delStmt->bindValue('id', $cid);
				$delStmt->execute();
				$delStmt2->bindValue('id', $cid);
				$delStmt2->execute();
			}
		}

		foreach ($this->definition->structure as $ck => $parent) {
			if($parent['parent'] === $key) {
				$stmt = $this->buildQuery('SELECT id FROM %s WHERE %s_id=:id', $ck, $key);
				$stmt->bindValue('id', $id);
				$stmt->execute();
				$cids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

				foreach($cids AS $cid) {
					$this->deleteNodeChildren($ck, $cid);
				}

				if($parent['reflexive']) {
					$stmt = $this->buildQuery('DELETE FROM %s_closure WHERE %s_id=:id', $ck, $key);
					$stmt->bindValue('id', $id);
					$stmt->execute();
				}

				$stmt = $this->buildQuery('DELETE FROM %s WHERE %s_id=:id', $ck, $key);
				$stmt->bindValue('id', $id);
				$stmt->execute();
			}
		}
	}

	public function updateNode($key, $id, $fieldData) {
		$fields = $this->definition->structure[$key]['fields'];
		$stmt = $this->buildQuery('UPDATE %s SET %s WHERE id=:id', $key, implode(',', array_map(function($f) {
			return sprintf('%s = :%s', $f, $f);
		}, $fields)));
		foreach ($fields as $field) {
			$stmt->bindValue($field, $fieldData[$field]);
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
			$validPositionStmt = $this->buildQuery('SELECT 1 FROM %s WHERE %s_id = :scope AND id = :id', $key, $parent);
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
				$stmt = $this->buildQuery('UPDATE %s SET %s_id = :parentId WHERE id = :id', $key, $parent);
				$stmt->bindValue('id', $id);
				$stmt->bindValue('parentId', $scopeId);
				$stmt->execute();

				if($reflexive) {
					// update scope of all trans children
					// UPDATE %s SET %s_id = :parentId WHERE id IN (SELECT child_id FROM %s_closure WHERE parent_id = :id)
					$othersStmt = $this->buildQuery('UPDATE %s SET %s_id = :parentId WHERE id IN (SELECT child_id FROM %s_closure WHERE parent_id = :id)', $key, $parent, $key);
					$othersStmt->bindValue('id', $id);
					$othersStmt->bindValue('parentId', $scopeId);
					$othersStmt->execute();

					// update scope trans closure
					// UPDATE %s_closure SET %s_id = :parentId WHERE parent_id = :id
					$closureStmt = $this->buildQuery('UPDATE %s_closure SET %s_id = :parentId WHERE child_id IN (SELECT child_id FROM %s_closure WHERE parent_id = :id)', $key, $parent, $key);
					$closureStmt->bindValue('id', $id);
					$closureStmt->bindValue('parentId', $scopeId);
					$closureStmt->execute();
				}
			}

			if($reflexive) {
				// delete old parents: DELETE FROM %s_closure WHERE child_id = :id AND depth > 0;
				// update closure edges
				$delStmt = $this->buildQuery('DELETE FROM %s_closure WHERE child_id = :id AND depth > 0', $key);
				$delStmt->bindValue('id', $id);
				$delStmt->execute();

				if($parent) {
					$insStmt = $this->buildQuery('INSERT INTO %s_closure (%s_id, parent_id, child_id, depth) VALUES(:scope, :parent, :child, :depth)', $key, $parent);
					$insStmt->bindValue('scope', $scopeId);
					$insStmt->bindValue('parent', $parentId);
					$insStmt->bindValue('child', $id);
					$insStmt->bindValue('depth', 1);
					$insStmt->execute();
				} else {
					$insStmt = $this->buildQuery('INSERT INTO %s_closure (parent_id, child_id, depth) VALUES(:parent, :child, :depth)', $key);
					$insStmt->bindValue('parent', $parentId);
					$insStmt->bindValue('child', $id);
					$insStmt->bindValue('depth', 1);
					$insStmt->execute();
				}

				$this->repairClosures();
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
				$stmt = $this->buildQuery('
					UPDATE %s AS outer
					SET %s = normalized FROM 
					(
						SELECT 
							h.id AS inner_id,
							ROW_NUMBER() OVER(PARTITION BY h.parent, h.%s_id ORDER BY h.%s ASC, h.id DESC) AS normalized FROM %s_hierarchy h INNER JOIN %s s ON s.id=h.id WHERE parent IS :parent AND h.%s_id=:scope
						
					) 
					WHERE outer.id = inner_id', 
					$key, $order, $parent, $order, $key, $key, $parent
				);
				$stmt->bindValue('parent', $parentId);
				$stmt->bindValue('scope', $scopeId);
				$stmt->execute();
			} else {
				$stmt = $this->buildQuery('
					UPDATE %s AS outer
					SET %s = normalized FROM 
					(
						SELECT 
							h.id AS inner_id,
							ROW_NUMBER() OVER(PARTITION BY %s_id ORDER BY %s ASC, id DESC) AS normalized FROM %s h WHERE %s_id=:scope
						
					) 
					WHERE outer.id = inner_id', 
					$key, $order, $parent, $order, $key, $parent
				);
				$stmt->bindValue('scope', $scopeId);
				$stmt->execute();
			}
		} else {
			if($reflexive) {
				$stmt = $this->buildQuery('
					UPDATE %s AS outer
					SET %s = normalized FROM 
					(
						SELECT 
							h.id AS inner_id,
							ROW_NUMBER() OVER(PARTITION BY parent ORDER BY s.%s ASC, s.id DESC) AS normalized FROM %s_hierarchy h INNER JOIN %s s ON h.id=s.id WHERE h.parent IS :parent
						
					) 
					WHERE outer.id = inner_id', 
					$key, $order, $order, $key, $key
				);
				$stmt->bindValue('parent', $parentId);
				$stmt->execute();
			} else {
				$stmt = $this->buildQuery('
					UPDATE %s AS outer
					SET %s = normalized FROM 
					(
						SELECT 
							id AS inner_id,
							ROW_NUMBER() OVER(ORDER BY %s ASC, id DESC) AS normalized FROM %s
						
					) 
					WHERE outer.id = inner_id', 
					$key, $order, $order, $key
				);
				$stmt->execute();
			}
		}
	}

	public function orderNodeUp($key, $id) {
		$self = $this->definition->structure[$key];
		$parent = $self['parent'];
		$order = $self['order'];


		$this->db->beginTransaction();

		$stmt = $this->buildQuery('SELECT CASE WHEN COUNT(DISTINCT equal.id) THEN MIN(equal.priority) + 1 ELSE MIN(greater.priority) END AS next
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
			GROUP BY self.id', 
			$key, $key, $parent, $parent, $key, $parent, $parent
		);
		$stmt->bindValue('id', $id);
		$stmt->execute();

		$newPos = $stmt->fetchColumn();

		if($newPos !== null) {
			$updateStmt = $this->buildQuery('UPDATE %s SET %s=:new WHERE id=:id', $key, $order);
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

		$stmt = $this->buildQuery('SELECT max(greater.priority)+1 next
			FROM %s_hierarchy self 
			LEFT JOIN %s_hierarchy greater 
			ON (self.%s_id, self.parent) IS (greater.%s_id, greater.parent) 
			AND greater.priority > self.priority 
			AND self.id <> greater.id
			WHERE self.id = :id
			GROUP BY self.id', 
			$key, $key, $parent, $parent
		);
		$stmt->bindValue('id', $id);
		$stmt->execute();

		$newPos = $stmt->fetchColumn();

		if($newPos !== null) {
			$updateStmt = $this->buildQuery('UPDATE %s SET %s=:new WHERE id=:id', $key, $order);
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

		$stmt = $this->buildQuery('SELECT CASE WHEN COUNT(DISTINCT equal.id) THEN MAX(equal.priority) - 1 ELSE MAX(lesser.priority) END AS next
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
			GROUP BY self.id', 
			$key, $key, $parent, $parent, $key, $parent, $parent
		);
		$stmt->bindValue('id', $id);
		$stmt->execute();

		$newPos = $stmt->fetchColumn();

		if($newPos !== null) {
			$updateStmt = $this->buildQuery('UPDATE %s SET %s=:new WHERE id=:id', $key, $order);
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

		$stmt = $this->buildQuery('SELECT MIN(lesser.priority)-1 AS next
			FROM %s_hierarchy self 
			LEFT JOIN %s_hierarchy lesser 
			ON (self.%s_id,self.parent) IS (lesser.%s_id,lesser.parent) 
			AND lesser.priority < self.priority 
			AND self.id <> lesser.id
			WHERE self.id = :id
			GROUP BY self.id', 
			$key, $key, $parent, $parent
		);
		$stmt->bindValue('id', $id);
		$stmt->execute();

		$newPos = $stmt->fetchColumn();

		if($newPos !== null) {
			$updateStmt = $this->buildQuery('UPDATE %s SET %s=:new WHERE id=:id', $key, $order);
			$updateStmt->bindValue('id', $id);
			$updateStmt->bindValue('new', $newPos);
			$updateStmt->execute();
		}

		$this->db->commit();
	}

	public function createNode($key, $fieldData, $scopeId = NULL, $parentId = NULL) {
		$reflexive = $this->definition->structure[$key]['reflexive'];
		$parent = $this->definition->structure[$key]['parent'];
		$fields = $this->definition->structure[$key]['fields'];
		$order = $this->definition->structure[$key]['order'];


		if(($parent===NULL) !== empty($scopeId)) {
			throw new ConsistencyException("parent");
		}

		if(($reflexive===FALSE) && !empty($parentId)) {
			throw new ConsistencyException($parentId);
		}

		if(!empty($scopeId) && !empty($parentId)) {
			$validPositionStmt = $this->buildQuery('SELECT 1 FROM %s WHERE %s_id = :scope AND id = :id', $key, $parent);
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
				$stmt = $this->buildQuery('INSERT INTO %s (%s_id, %s) VALUES (:parentId, %s)', $key, $parent, implode(',', $fields), ':' . implode(', :', $fields));
				$stmt->bindValue('parentId', $scopeId);
				foreach ($fields as $field) {
					$stmt->bindValue($field, $fieldData[$field]);
				}
				$stmt->execute();

				$lastId = $this->db->lastInsertId();
				if($reflexive) {
					$stmt = $this->buildQuery('INSERT INTO %s_closure (%s_id, parent_id, child_id, depth) VALUES (:scope, :parent,:child,:depth)', $key, $parent);
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
				$stmt = $this->buildQuery('INSERT INTO %s (%s) VALUES (%s)', $key, implode(',', $fields), ':' . implode(', :', $fields));
				foreach ($fields as $field) {
					$stmt->bindValue($field, $fieldData[$field]);
				}
				$stmt->execute();
				$lastId = $this->db->lastInsertId();
				if($reflexive) {
					$stmt = $this->buildQuery('INSERT INTO %s_closure (parent_id, child_id, depth) VALUES (:parent,:child,:depth)', $key);
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
			$this->repairClosures();

			if($order) {
				$this->normalizeRowOrder($key, $scopeId?:null, $parentId?:null);
			}

			$this->db->commit();
		} catch(\Exception $e) {
			$this->db->rollback();
			throw $e;
		}
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
		$reflexive = $definition['reflexive'];
		$order = $definition['order'];

		$columnSql = implode("\n", array_map(function($field) {
			return sprintf('"%s"	TEXT NOT NULL,', $field);
		}, $definition['fields']));

		if($order) {
			$columnSql .= sprintf('"%s"	INTEGER NOT NULL DEFAULT 0,', $order);
		}

		if($parent) {
			return str_replace(['%NAME%', '%PARENT%', '%COLUMNS%'],[$table, $parent, $columnSql], <<<SQL
				CREATE TABLE IF NOT EXISTS "%NAME%" (
					"id"	INTEGER NOT NULL,
					%COLUMNS%
					"%PARENT%_id"	INTEGER NOT NULL,
					FOREIGN KEY("%PARENT%_id") REFERENCES "%PARENT%"("id") ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
					PRIMARY KEY("id" AUTOINCREMENT),
					UNIQUE("id","%PARENT%_id")
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

	private function generatorSQL($table) {
		return str_replace(['%NAME%'],[$table], <<<SQL
			CREATE TABLE IF NOT EXISTS "%NAME%_generator" (
				"id"	INTEGER NOT NULL,
				"query" TEXT NOT NULL,
				"%NAME%_id"	INTEGER NOT NULL,
				FOREIGN KEY("%NAME%_id") REFERENCES "%NAME%"("id") ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
				PRIMARY KEY("id" AUTOINCREMENT),
				UNIQUE("%NAME%_id")
			);
		SQL);
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
			return str_replace(['%NAME%'], [$table], <<<SQL
				SELECT
					parent.id AS parent,
					self.id AS id,
					self.slug 
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
				    parent.id ASC,
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
					a.depth + b.depth AS depth
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
					0 AS depth
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
					a.depth + b.depth AS depth
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
					0 AS depth
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
					t.parent_id AS x,
					t.child_id AS y,
					t.depth AS depth
				FROM
					%NAME%_closure t
				WHERE ((
					t.depth = 0
					AND
					t.child_id <> t.parent_id
				) OR (
					t.depth <> 0
					AND
					t.child_id IS t.parent_id
				) OR (
					t.depth > 1
					AND
					NOT EXISTS (
						SELECT
							a.id
						FROM
							%NAME%_closure a
						INNER JOIN %NAME%_closure b
						ON a.child_id = b.parent_id
						WHERE
							(a.%SCOPE%_id, b.%SCOPE%_id) IS (t.%SCOPE%_id, t.%SCOPE%_id)
							AND (a.depth + b.depth) IS t.depth
							AND a.id <> t.id
							AND b.id <> t.id
							AND (t.parent_id, t.child_id)
							IS (a.parent_id, b.child_id)
					)
				) OR (	
					t.child_id <> t.parent_id
					AND
					EXISTS (
						SELECT
							r.id
						FROM
							%NAME%_closure r
						WHERE
							(r.child_id, r.parent_id) = (t.parent_id, t.child_id)
					)
				))
			SQL);
		} else {
			return str_replace(['%NAME%'], [$table], <<<SQL
				SELECT
					t.id AS id,
					t.parent_id AS x,
					t.child_id AS y,
					t.depth AS depth
				FROM
					%NAME%_closure t
				WHERE ((
					t.depth = 0
					AND
					t.child_id <> t.parent_id
				) OR (
					t.depth <> 0
					AND
					t.child_id IS t.parent_id
				) OR (
					t.depth > 1
					AND
					NOT EXISTS (
						SELECT
							a.id
						FROM
							%NAME%_closure a
						INNER JOIN %NAME%_closure b
						ON a.child_id = b.parent_id
						WHERE
							(a.depth + b.depth) IS t.depth
							AND a.id <> t.id
							AND b.id <> t.id
							AND (t.parent_id, t.child_id)
							IS (a.parent_id, b.child_id)
					)
				))
			SQL);
		}
	}
	
	private function buildQuery($template, ...$params) {
		$q = sprintf($template, ...$params);
		return $this->db->prepare($q);
	}
}