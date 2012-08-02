<?php

defined('COT_CODE') or die('Wrong URL.');

(function_exists('version_compare') && version_compare(PHP_VERSION, '5.3.0', '>=')) or die('PHP version 5.3 or higher is required.');

require_once cot_incfile('forms');
require_once cot_incfile('orm');

/**
 * View class for Cotonti. Defines how objects should be displayed.
 *
 * View classes should extend CotView to inherit its methods
 * and must specify $model_class and $columns
 *
 * @package Cotonti
 * @version 1.3
 * @author Gert Hengeveld, Vladimir Sibirov
 * @copyright (c) Cotonti Team 2012
 * @license BSD
 */
abstract class CotView
{
	/**
	 * Custom display properties for columns
	 * @var array
	 */
	protected static $properties = array();
	/**
	 * Model object containing data
	 * @var CotORM
	 */
	protected $model = null;
	/**
	 * Template object
	 * @var XTemplate
	 */
	protected $tpl = null;
	/**
	 * Item permissions, such as R/W/A
	 * @var array
	 */
	protected $rights = array();


	public function __construct($model, $tpl, $rights = array('R' => TRUE, 'W' => TRUE, 'A' => FALSE))
	{
		$this->model = $model;
		$this->tpl = $tpl;
		$this->rights = $rights;
	}

	/**
	 * Displays the object by assigning template tags for its properties.
	 * @param  string $tag_prefix Prefix for TPL tags
	 * @param  string $block      Full block name to render after tags are assigned, e.g. 'MAIN.ITEM_ROW'.
	 * Omit it if you want to parse the block elsewhere.
	 */
	public function display($tag_prefix = '', $block = '')
	{
		// Display all model properties
		$class = get_class($this->model);
		$cols = $class::columns(TRUE, FALSE);
		foreach ($this->model as $prop => $val)
		{
			//$props = self::$properties[$prop];
			if ($cols[$prop]['foreign_key'])
			{
				// TODO show linked data
			}
			else
			{
				$this->tpl->assign(array(
					$tag_prefix . mb_strtoupper($prop) => $this->rights['R'] ? $val : ''
				));
			}
		}

		if (!empty($block))
			$this->tpl->parse($block);
	}

	public function displayForm($tag_prefix = '', $block = '', $url = '')
	{
		$class = get_class($this->model);
		$cols = $class::columns(TRUE, FALSE);
		foreach ($this->model as $prop => $val)
		{
			//$props = self::$properties[$prop];
			if ($cols[$prop]['foreign_key'])
			{
				// TODO show linked data
			}
			else
			{
				$this->tpl->assign(array(
					$tag_prefix . mb_strtoupper($prop) => $this->rights['R'] ? $val : ''
				));
			}
		}

		if (!empty($block))
			$this->tpl->parse($block);
	}

	public static function displayList($items, $tag_prefix = '', $block = '')
	{

	}

	public static function displayListForm($items, $tag_prefix = '', $block = '', $url = '')
	{

	}
}
