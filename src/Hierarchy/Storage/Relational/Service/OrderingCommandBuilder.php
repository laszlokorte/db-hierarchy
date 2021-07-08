<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Storage\Relational\Naming;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;

class OrderingCommandBuilder  {

	public function __construct(private SchemaDefinition $schemaDef, private Naming $naming, private ColumnCoder $coder) {
	}

	public function getUpdateforReorderNode(string $keyId, Parameter $idParam, Parameter $orderParam) {
		// UPDATE sorted_tree AS t
		// SET priority=inn.new_order
		// FROM (
		// SELECT 
		// o._id AS id, 
		// o._normalized_order AS prev_order,
		// CASE
		// WHEN s._id = o._id 
		// THEN 9
		// WHEN 9 < s._normalized_order AND (o._normalized_order BETWEEN 9 AND s._normalized_order-1)
		// THEN o._normalized_order + 1
		// WHEN 9 > s._normalized_order AND  (o._normalized_order BETWEEN s._normalized_order+1 AND 9)
		// THEN o._normalized_order - 1
		// ELSE o._normalized_order
		// END AS new_order
		// FROM _sorted_tree_normalized_order s
		// LEFT JOIN _sorted_tree_normalized_order o 
		// ON (s._parent, s._scope) IS (o._parent, o._scope)
		// WHERE s._id = 11
		// ) AS inn
		// WHERE inn.id = t.id AND inn.prev_order <> inn.new_order
		$table = new TableReference($this->naming->nodeTableName($keyId));
		$normalizedViewSelf = new TableReference($this->naming->normalizedOrderViewName($keyId), new Identifier('normalized_self'));
		$normalizedViewSiblings = new TableReference($this->naming->normalizedOrderViewName($keyId), new Identifier('normalized_siblings'));

		$normalizedOrderSelf = new ColumnReference($normalizedViewSelf, $this->naming->normalizedOrderNormalizedColumnName($keyId));
		$normalizedOrderSibling = new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderNormalizedColumnName($keyId));

		$idSelf = new ColumnReference($normalizedViewSelf, $this->naming->normalizedOrderIdColumnName($keyId));
		$idSibling = new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderIdColumnName($keyId));

		$innerId = new Projection(new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderIdColumnName($keyId)), new Identifier('innerid'));
		$innerNew = new Projection(
			new Cases(
				new BinaryOperation(new Equal(), $idSelf, $idSibling), 
				$orderParam,

				// 9 < s._normalized_order AND (o._normalized_order BETWEEN 9 AND s._normalized_order-1)

				// 9 < s._normalized_order AND 
				// 9 <= o._normalized_order  AND 
				// o._normalized_order <= s._normalized_order-1)
				new AssociativeOperation(new Conjunction(), [
					new BinaryOperation(new LessThan(), $orderParam, $normalizedOrderSelf),
					new BinaryOperation(new LessThanEqual(), $orderParam, $normalizedOrderSibling,
					),
					new BinaryOperation(new LessThanEqual(), $normalizedOrderSibling, new BinaryOperation(new Subtraction(), $normalizedOrderSelf, new Constant(1)))
				]), 
				new BinaryOperation(new Addition(), $normalizedOrderSibling, new Constant(1)),


				// 9 > s._normalized_order AND  (o._normalized_order BETWEEN s._normalized_order+1 AND 9)

				// 9 > s._normalized_order AND  
				// ( s._normalized_order+1 <= o._normalized_order AND 
				//o._normalized_order <= 9)
				new AssociativeOperation(new Conjunction(), [
					new BinaryOperation(new GreaterThan(), $orderParam, $normalizedOrderSelf),
					new BinaryOperation(new LessThanEqual(),
						new BinaryOperation(new Addition(), $normalizedOrderSelf, new Constant(1)), $normalizedOrderSibling
					),
					new BinaryOperation(new LessThanEqual(), $normalizedOrderSibling, $orderParam)
				]),
				new BinaryOperation(new Subtraction(), $normalizedOrderSibling, new Constant(1)),

				$normalizedOrderSibling
			)
		, new Identifier('innernew'));
		
		$innerOld = new Projection($normalizedOrderSibling, new Identifier('innerold'));

		return new Update($table, [
			new Setter(new ColumnReference($table, $this->naming->orderColumnName($keyId)), new Projected($innerNew))
		], new BinaryOperation(
			new Conjunction(),
			new BinaryOperation(
				new Equal(),
				new Projected($innerId),
				new ColumnReference($table, $this->naming->nodeTablePKName($keyId))
			),
			new BinaryOperation(
				new NotEqual(),
				new Projected($innerNew),
				new Projected($innerOld)
			)
		), new Select([
			$innerNew,
			$innerId,
			$innerOld,
		], [$normalizedViewSelf], [
			new Join($normalizedViewSiblings, new BinaryOperation(
				new Equal(true),
				new Tuple([
					new ColumnReference($normalizedViewSelf, $this->naming->normalizedOrderScopeColumnName($keyId)), 
					new ColumnReference($normalizedViewSelf, $this->naming->normalizedOrderParentColumnName($keyId))]),
				new Tuple([
					new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderScopeColumnName($keyId)), 
					new ColumnReference($normalizedViewSiblings, $this->naming->normalizedOrderParentColumnName($keyId))])
			), 'LEFT')
		], new BinaryOperation(new Equal(), $idSelf, $this->coder->wrapPrimaryKeyParameter($keyId, $idParam))));
	}
}