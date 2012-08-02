<?php
/**
 * Test file for CotORM
 * @package Cotonti
 * @version 1.2
 * @author Gert Hengeveld, Vladimir Sibirov
 * @copyright (c) Cotonti Team 2012
 * @license BSD
 */

defined('COT_CODE') or die('Wrong URL.');

require_once cot_incfile('orm');

// Example model
class TestProject extends CotORM
{
	protected static $table_name = 'projects';
	protected static $columns = array(
		'id' => array(
			'type' => 'int',
			'primary_key' => true,
			'auto_increment' => true,
			'locked' => true
		),
		'ownerid' => array(
			'type' => 'int',
			'foreign_key' => 'users:user_id',
			'locked' => true
		),
		'name' => array(
			'type' => 'varchar',
			'length' => 50,
			'unique' => true
		),
		'metadata' => array(
			'type' => 'object'
		),
		'type' => array(
			'type' => 'varchar',
			'length' => 6
		),
		'created' => array(
			'type' => 'int',
			'on_insert' => 'NOW()',
			'locked' => true
		),
		'updated' => array(
			'type' => 'int',
			'on_insert' => 'NOW()',
			'on_update' => 'NOW()',
			'locked' => true
		)
	);
}

// Prepare for a test
function setup_orm()
{
	TestProject::createTable();
}

// Cleanup the environment
function teardown_orm()
{
	TestProject::dropTable();
}

// Tests basic ORM features
function test_orm_basic()
{
	// Add a simple record
	$obj = new TestProject(array(
		'name' => 'Test',
		'metadata' => array(
			'description' => 'Some testing project'
		),
		'type' => 'INTERN',
		'ownerid' => 1
	));
	if (!$obj->insert())
	{
		return 'Could not insert a new project';
	}

	// Emulate import from POST
	$_POST['name'] = 'Test post';
	$_POST['type'] = 'INTERN';
	$_POST['ownerid'] = 1;
	$obj = TestProject::import('POST');
	if (!$obj->insert())
	{
		return 'Imported object could be inserted';
	}

	// Find the first project
	$obj = TestProject::findByPk(1);
	if (is_null($obj))
	{
		return 'findByPk() returned null';
	}
	if ($obj->name !== 'Test')
	{
		return 'First project name is not Test';
	}

	// Fetch all objects at once
	$projs = TestProject::findAll();
	if (count($projs) !== 2)
	{
		return 'findAll() returned too few items (' . count($projs) . ')';
	}

	// Update an item
	$obj = TestProject::findByPk(2);
	$obj->type = 'EXTERN';
	if (!$obj->update())
	{
		return 'Could not update an object';
	}
	if (count(TestProject::find("type = 'EXTERN'")) == 0)
	{
		return 'Update has not changed column value: ' . TestProject::findByPk(2)->type;
	}

	// Remove an item
	TestProject::delete('id = 1');
	$count = TestProject::count();
	if ($count !== 1)
	{
		return 'Invalid number of items after delete: ' . $count;
	}

	return TRUE;
}

// Tests iteration over object properties
function test_orm_iterator()
{
	// Add a simple record
	$obj = new TestProject(array(
		'name' => 'Iterate',
		'type' => 'TEST',
		'ownerid' => 1
	));
	if (!$obj->insert())
	{
		return 'Could not insert a new project';
	}

	// Fetch a record and iterate over its properties
	$obj = TestProject::findOne("name = 'Iterate'");
	if (is_null($obj))
	{
		return 'find() returned null';
	}

	$valid_cols = array_keys(TestProject::columns(true, true));
	foreach ($obj as $key => $val)
	{
		if ($key === 'name' && $val != 'Iterate')
			return "Wrong name: $val";
		elseif ($key === 'type' && $val != 'TEST')
			return "Wrong type: $val";
		elseif ($key === 'ownerid' && $val != 1)
			return "Wrong ownerid: $val";
		elseif (!in_array($key, $valid_cols))
			return "Invalid colname: $key";
	}

	// All was ok
	return TRUE;
}
