<?php
/**
 * Test file for CotView
 * @package Cotonti
 * @author Gert Hengeveld, Vladimir Sibirov
 * @copyright (c) Cotonti Team 2012
 * @license BSD
 */

defined('COT_CODE') or die('Wrong URL.');

require_once cot_incfile('view');

// Use models from the ORM test
require_once 'test/orm.test.php';

class TestProjectView extends CotView
{
	// TODO code here
}

// Prepare for a test
function setup_view()
{
	setup_orm();
}

// Cleanup the environment
function teardown_view()
{
	teardown_orm();
}

// Test the view's display method
function test_view_display()
{
	// Add a simple record
	$obj = new TestProject(array(
		'name' => 'View Test',
		'metadata' => array(
			'description' => 'Testing the view class'
		),
		'type' => 'VIEW',
		'ownerid' => 1
	));
	if (!$obj->save())
	{
		return 'Could not insert a new project';
	}

	// Load the template and create the view
	$tpl = new XTemplate('test/tpl/view.test.tpl');
	$view = new TestProjectView($obj, $tpl);

	// Try to display the item
	$view->display('TEST_', 'MAIN');

	// Check manually whether the tags were assigned and the block parsed
	if ($tpl->vars['TEST_NAME'] != $obj->name
		|| $tpl->vars['TEST_METADATA']['description'] != $obj->metadata['description']
		|| $tpl->vars['TEST_TYPE'] != $obj->type
		)
	{
		return 'Tags were not assigned properly';
	}

	$text = $tpl->text('MAIN');
	if (empty($text))
	{
		return 'Block was not parsed';
	}

	// All was OK
	return TRUE;
}
