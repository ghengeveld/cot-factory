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
