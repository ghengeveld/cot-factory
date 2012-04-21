<?php

defined('COT_CODE') or die('Wrong URL.');

(function_exists('version_compare') && version_compare(PHP_VERSION, '5.3.0', '>=')) or die('PHP version 5.3 or higher is required.');

require_once cot_incfile('cotemplate');
require_once cot_incfile('orm');

/**
 * Basic View class
 * 
 * @package Cotonti
 * @version 1.3
 * @author Vladimir Sibirov
 * @copyright (c) Cotonti Team 2011-2012
 * @license BSD
 */
class CotView extends XTemplate
{
	/**
	 * Binds a data object to template tags
	 * @param CotORM $obj Data object
	 * @param string $prefix Tag prefix
	 */
	public function bind($obj, $prefix = '')
	{
		// TODO...
	}

	/**
	 * Generates form inputs for an object and binds them to template tags
	 * @param CotORM $obj Data object
	 * @param string $prefix Tag prefix
	 * @param string $index Row index for multi-edit (grid) forms
	 */
	public function bindForm($obj, $prefix = '', $index = '')
	{
		// TODO...
	}
}

?>
