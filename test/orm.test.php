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
 //          'foreign_key' => 'users:user_id',
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

// Run this to fail the test shortly
function orm_test_fail($msg = '')
{
	if (!empty($msg))
	{
		cot_error($msg);
	}
	TestProject::dropTable();
	return false;
}

// Tests basic ORM features
function test_orm_basic()
{
	TestProject::createTable();

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
		return orm_test_fail('Could not insert a new project');
	}

	// Emulate import from POST
	$_POST['name'] = 'Test post';
	$_POST['type'] = 'INTERN';
	$_POST['ownerid'] = 1;
	$obj = TestProject::import('POST');
	if (!$obj->insert())
	{
		return orm_test_fail('Imported object could be inserted');
	}
	
	// Find the first project
	$obj = TestProject::findByPk(1);
	if (is_null($obj))
	{
		return orm_test_fail('findByPk() returned null');
	}
	if ($obj->data('name') !== 'Test')
	{
		return orm_test_fail('First project name is not Test');
	}
	
	// Fetch all objects at once
	$projs = TestProject::findAll();
	if (count($projs) !== 2)
	{
		return orm_test_fail('findAll() returned too few items (' . count($projs) . ')');
	}
	
	// Update an item
	$obj = TestProject::findByPk(2);
	$obj->data('type', 'EXTERN');
	if (!$obj->update())
	{
		return orm_test_fail('Could not update an object');
	}
	if (count(TestProject::find("type = 'EXTERN'")) == 0)
	{
		return orm_test_fail('Update has not changed column value: ' . TestProject::findByPk(2)->data('type'));
	}
	
	// Remove an item
	TestProject::delete('id = 1');
	$count = TestProject::count();
	if ($count !== 1)
	{
		return orm_test_fail('Invalid number of items after delete: ' . $count);
	}
	
	TestProject::dropTable();
	return true;
}

?>
