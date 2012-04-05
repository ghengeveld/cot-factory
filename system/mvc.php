<?php

defined('COT_CODE') or die('Wrong URL.');

/**
 * Helper for Model-View-Controller module development
 *
 * @package Cotonti
 * @version 1.0
 * @author Gert Hengeveld
 * @copyright (c) Cotonti Team 2011
 * @license BSD
 */

spl_autoload_register('mvc_autoload', true, true);

/**
 * Autoloader for models and views
 *
 * @param string $class Class name
 */
function mvc_autoload($class)
{
	global $cfg, $env;
	$paths = array(
		"{$cfg['modules_dir']}/{$env['ext']}/models/$class.php",
		"{$cfg['modules_dir']}/{$env['ext']}/views/$class.{$env['formatting']}.php"
	);
	foreach ($paths as $path)
	{
		if (file_exists($path))
		{
			require $path;
			return;
		}
	}
}

/**
 * Calls controller function or method.
 * 
 * Controller file can contain a class with methods which will map to $action, 
 * or plain functions which should be named like $controller_$action
 * 
 * @param string $controller Controller filename
 * @param string $action Function or method to call.
 * @return bool TRUE on success, FALSE on failure
 */
function mvc_dispatch($controller, $action = null)
{
	$method = strtolower($_SERVER['REQUEST_METHOD']);
	$action = $action ? $method.'_'.$action : $method.'_index';

	if (file_exists("{$cfg['modules_dir']}/{$env['ext']}/controllers/$controller.php"))
	{
		require_once "{$cfg['modules_dir']}/{$env['ext']}/controllers/$controller.php";
	}

	if (is_callable("{$controller}_{$action}"))
	{
		call_user_func("{$controller}_{$action}");
		return TRUE;
	}
	else
	{
		$controller = ucfirst($controller);
		if (is_callable(array($controller.'Controller', $action)))
		{
			call_user_func(array($controller.'Controller', $action));
			return TRUE;
		}
	}
	return FALSE;
}

?>