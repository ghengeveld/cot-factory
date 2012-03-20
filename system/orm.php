<?php

defined('COT_CODE') or die('Wrong URL.');

require_once cot_langfile('orm', 'core');

/**
 * Basic ORM for Cotonti
 *
 * Model classes should extend CotORM to inherit its methods
 * and must specify $class_name, $table_name and $columns
 *
 * @package Cotonti
 * @version 1.2
 * @author Gert Hengeveld
 * @copyright (c) Cotonti Team 2011
 * @license BSD
 */
abstract class CotORM
{
	protected $class_name = '';
	protected $table_name = '';
	protected $columns = array();
	protected $data = array();

	/**
	 * Include reference to $db for easy access in methods.
	 * Object data can be passed
	 *
	 * @param type $data
	 */
	public function __construct($data = array())
	{
		global $db;
		$this->db = $db;
		$this->data = $data;
		$this->class_name = get_class($this);
	}

	/**
	 * Returns table name including prefix.
	 *
	 * @return type
	 */
	protected function tableName()
	{
		global $db_x;
		return $db_x.$this->table_name;
	}

	/**
	 * Returns primary key column name. Defaults to 'id' if none was set.
	 *
	 * @return string
	 */
	protected function primaryKey()
	{
		foreach ($this->columns as $column => $info)
		{
			if ($info['primary_key']) return $column;
		}
		return 'id';
	}

	/**
	 * Getter and setter for object data
	 *
	 * @param string $key Optional key to fetch a single value
	 * @param mixed $value Optional value to set
	 * @return mixed
	 *	array of key->value pairs, or
	 *  string value if $key was provided, or
	 *  boolean if $value was provided
	 */
	public function data($key = null, $value = null)
	{
		if ($key !== null && $value !== null)
		{
			if (array_key_exists($key, $this->columns) && !$this->columns[$key]['locked'])
			{
				$this->data[$key] = $value;
				return true;
			}
			return false;
		}
		else
		{
			if ($key !== null)
			{
				if (array_key_exists($key, $this->columns) && !$this->columns[$key]['hidden'])
				{
					return ($this->columns[$key]['type'] == 'object') ?
						unserialize($this->data[$key]) : $this->data[$key];
				}
			}
			else
			{
				$data = array();
				foreach ($this->columns as $key => $val)
				{
					if ($this->columns[$key]['hidden']) continue;
					$data[$key] = ($this->columns[$key]['type'] == 'object') ?
						unserialize($this->data[$key]) : $this->data[$key];
				}
				return $data;
			}
		}
	}

	/**
	 * Returns object data column definitions
	 *
	 * @param bool $include_locked Return locked columns?
	 * @param bool $include_hidden Return hidden columns?
	 * @return array
	 */
	public function columns($include_locked = false, $include_hidden = false)
	{
		$cols = array();
		foreach ($this->columns as $key => $val)
		{
			if (!$include_hidden && $this->columns[$key]['hidden']) continue;
			if (!$include_locked && $this->columns[$key]['locked']) continue;
			$cols[$key] = $this->columns[$key];
		}
		return $cols;
	}

	/**
	 * Count records matching condition
	 *
	 * @param mixed $conditions Numeric array of SQL WHERE conditions or a single
	 *  condition as a string
	 * @return int Number of records found
	 */
	public static function count($conditions)
	{
		CotORM::checkPHPVersion();
		$obj = new static();
		return $obj->countRows($conditions);
	}

	/**
	 * Retrieve all existing objects from database
	 *
	 * @param mixed $conditions Numeric array of SQL WHERE conditions or a single
	 *  condition as a string
	 * @param int $limit Maximum number of returned objects
	 * @param int $offset Offset from where to begin returning objects
	 * @param string $order Column name to order on
	 * @param string $way Order way 'ASC' or 'DESC'
	 * @return array
	 */
	public static function find($conditions, $limit = 0, $offset = 0, $order = '', $way = 'ASC')
	{
		CotORM::checkPHPVersion();
		$obj = new static();
		return $obj->fetch($conditions, $limit, $offset, $order, $way);
	}

	/**
	 * Retrieve all existing objects from database
	 *
	 * @param int $limit Maximum number of returned objects
	 * @param int $offset Offset from where to begin returning objects
	 * @param string $order Column name to order on
	 * @param string $way Order way 'ASC' or 'DESC'
	 * @return array
	 */
	public static function findAll($limit = 0, $offset = 0, $order = '', $way = 'ASC')
	{
		CotORM::checkPHPVersion();
		$obj = new static();
		return $obj->fetch(array(), $limit, $offset, $order, $way);
	}

	/**
	 * Retrieve existing object from database by primary key
	 *
	 * @param mixed $pk Primary key
	 * @return object
	 */
	public static function findByPk($pk)
	{
		CotORM::checkPHPVersion();
		$obj = new static();
		$res = $obj->fetch("{$obj->primaryKey()} = '$pk'", 1);
		return ($res) ? $res[0] : null;
	}

	/**
	 * Get all objects from the database matching given conditions
	 *
	 * @param mixed $conditions Array of SQL WHERE conditions or a single
	 *  condition as a string
	 * @param int $limit Maximum number of returned records or 0 for unlimited
	 * @param int $offset Return records starting from offset (requires $limit > 0)
	 * @return array List of objects matching conditions or null
	 */
	protected function fetch($conditions = array(), $limit = 0, $offset = 0, $order = '', $way = 'DESC')
	{
		global $db_x;
		$table = $this->tableName();
		$columns = array();
		$joins = array();
		$obj = new $this->class_name();
		$cols = $obj->columns(true, true);
		foreach ($cols as $col => $data)
		{
			$columns[] = "`$table`.`$col`";
			if ($data['foreign_key'] && strpos($data['foreign_key'], ':') !== null)
			{
				list($table_fk, $col_fk) = explode(':', $data['foreign_key']);
				$table_fk = $db_x.$table_fk;
				$table_fk_alias = cot_unique(8);
				$columns[] = "`$table_fk_alias`.`$col_fk`";
				$joins[] = "LEFT JOIN `$table_fk` AS `$table_fk_alias` ON `$table`.`$col` = `$table_fk_alias`.`$col_fk`";
			}
		}
		$columns = implode(', ', $columns);
		$joins = implode(' ', $joins);

		list($where, $params) = $this->parseConditions($conditions);

		$order = ($order) ? "ORDER BY `$order` $way" : '';
		$limit = ($limit) ? "LIMIT $offset, $limit" : '';

		$objects = array();
		$res = $this->db->query("
			SELECT $columns FROM $table $joins $where $order $limit
		", $params);
		while ($row = $res->fetch(PDO::FETCH_ASSOC))
		{
			$obj = new $this->class_name();
			$obj->data = $row;
			$objects[] = $obj;
		}
		return (count($objects) > 0) ? $objects : null;
	}

	/**
	 * Returns SQL COUNT for given conditions
	 *
	 * @param mixed $conditions Array of SQL WHERE conditions or a single
	 *  condition as a string
	 * @return int
	 */
	public function countRows($conditions)
	{
		list($where, $params) = $this->parseConditions($conditions);

		return (int)$this->db->query("
			SELECT COUNT(*) FROM ".$this->tableName()." $where
		", $params)->fetchColumn();
	}

	/**
	 * Parses query conditions from string or array
	 *
	 * @param mixed $conditions SQL WHERE conditions as string or numeric array of strings
	 * @param array $params Optional PDO params to pass through
	 * @return array SQL WHERE part and PDO params
	 */
	protected function parseConditions($conditions, $params = array())
	{
		$where = '';
		$table = $this->tableName();
		if (!is_array($conditions)) $conditions = array($conditions);
		if (count($conditions) > 0)
		{
			$where = array();
			foreach ($conditions as $condition)
			{
				$parts = array();
				preg_match_all('/(.+?)([<>= ]+)(.+)/', $condition, $parts);
				$column = trim($parts[1][0]);
				$operator = trim($parts[2][0]);
				$value = trim(trim($parts[3][0]), '\'"`');
				if ($column && $operator)
				{
					$where[] = "`$table`.`$column` $operator :$column";
					if (intval($value) == $value) $value = intval($value);
					$params[$column] = $value;
				}
			}
			$where = 'WHERE '.implode(' AND ', $where);
		}
		return array($where, $params);
	}

	/**
	 * Reload object data from database
	 *
	 * @return bool
	 */
	protected function loadData()
	{
		$pk = $this->primaryKey();
		if (!$this->data[$pk]) return false;
		$res = $this->db->query("
			SELECT *
			FROM `".$this->tableName()."`
			WHERE `$pk` = ?
			LIMIT 1
		", array($this->data[$pk]))->fetch(PDO::FETCH_ASSOC);

		if ($res)
		{
			$this->data = $res;
			return true;
		}
		return false;
	}

	/**
	 * Verifies query data to meet column rules
	 *
	 * @param array $data Query data
	 * @return bool
	 */
	protected function validateData($data, $for = 'insert')
	{
		global $db_x;
		if (!is_array($data)) return;

		foreach ($data as $column => $value)
		{
			if (!isset($this->columns[$column]))
			{
				cot_error(cot_rc("InvalidColumnName", array('column' => $column)), $column);
				return FALSE;
			}
			if ($this->columns[$column]['auto_increment'])
			{
				cot_error(cot_rc("CantUpdateAutoIncrementColumn", array($column)), $column);
				return FALSE;
			}
			if ($for == 'update' && $this->columns[$column]['primary_key'])
			{
				cot_error(cot_rc("CantUpdatePrimaryKeyColumn", array('column' => $column)), $column);
				return FALSE;
			}
			if ($for == 'update' && $this->columns[$column]['locked'])
			{
				cot_error(cot_rc("CantUpdateLockedColumn", array('column' => $column)), $column);
				return FALSE;
			}
			if (isset($this->columns[$column]['minlength']) && mb_strlen($value) < $this->columns[$column]['minlength'])
			{
				cot_error(cot_rc("ValueIsBelowMinimumLength", array(
					'column' => $column,
					'minlength' => $this->columns[$column]['minlength'],
					'length' => mb_strlen($value)
				)), $column);
				return FALSE;
			}
			if (isset($this->columns[$column]['maxlength']) && mb_strlen($value) > $this->columns[$column]['maxlength'])
			{
				cot_error(cot_rc("ValueExceedsMaximumLength", array(
					'column' => $column,
					'maxlength' => $this->columns[$column]['maxlength'],
					'length' => mb_strlen($value)
				)), $column);
				return FALSE;
			}
			if (isset($this->columns[$column]['validators']))
			{
				if (!is_array($this->columns[$column]['validators']))
				{
					$this->columns[$column]['validators'] = array($this->columns[$column]['validators']);
				}
				foreach ($this->columns[$column]['validators'] as $validator)
				{
					if (function_exists($validator))
					{
						if (!call_user_func($validator, $value, $column)) return FALSE;
					}
					elseif (method_exists($this, $validator))
					{
						if (!call_user_func(array($this, $validator), $value, $column)) return FALSE;
					}
				}
			}
			$typecheck_pass = TRUE;
			switch ($this->columns[$column]['type'])
			{
				case 'int':
					if (!is_int($value)) $typecheck_pass = FALSE;
					break;
				case 'char':
				case 'varchar':
				case 'text':
					if (!is_string($value)) $typecheck_pass = FALSE;
					break;
				case 'decimal':
				case 'float':
					if (!is_int($value) && !is_double($value) && !is_float($value)) $typecheck_pass = FALSE;
					break;
			}
			if (!$typecheck_pass)
			{
				cot_error(cot_rc("InvalidVariableType", array(
					'column' => $column,
					'type' => gettype($value)
				)), $column);
				return FALSE;
			}
			if ($this->columns[$column]['foreign_key'] && $this->columns[$column]['default_value'] !== $value)
			{
				$fk = explode(':', $this->columns[$column]['foreign_key']);
				if (count($fk) == 2 && $this->db->fieldExists($db_x.$fk[0], $fk[1]))
				{
					if ($this->db->query("SELECT `{$fk[1]}` FROM `$db_x{$fk[0]}` WHERE `{$fk[1]}` = ?", array($value))->rowCount() == 0)
					{
						cot_error(cot_rc("ForeignKeyCheckFailed", array(
							'column' => $column,
							'table' => $db_x.$fk[0],
							'key' => $fk[1],
							'value' => $value
						)), $column);
						return FALSE;
					}
				}
			}
		}
		return TRUE;
	}

	/**
	 * Prepare query data for usage
	 *
	 * @param array $data Query data as column => value pairs
	 * @param string $for 'insert' or 'update'
	 * @return array Prepared query data
	 */
	protected function prepData($data, $for)
	{
		global $sys;
		foreach ($this->columns as $column => $rules)
		{
			if ($for == 'insert' && isset($rules['on_insert']) && !isset($data[$column]))
			{
				if (is_string($rules['on_insert']))
				{
					if ($rules['on_insert'] === 'NOW()')
					{
						$rules['on_insert'] = $sys['now'];
					}
					if ($rules['on_insert'] === 'RANDOM()')
					{
						if ($rules['type'] == 'varchar')
						{
							$length = ($rules['maxlength']) ? (int)$rules['maxlength'] : 255;
							$rules['on_insert'] = cot_randomstring($length);
						}
						elseif ($rules['type'] == 'int')
						{
							$length = ($rules['maxlength']) ? (int)$rules['maxlength'] : 10;
							$rules['on_insert'] = mt_rand(pow(10, $length-1), pow(10, $length)-1);
						}
					}
				}
				$data[$column] = $rules['on_insert'];
			}
			if ($for == 'update' && isset($rules['on_update']) && !isset($data[$column]))
			{
				if (is_string($rules['on_update']))
				{
					if ($rules['on_update'] === 'NOW()')
					{
						$rules['on_update'] = $sys['now'];
					}
					if ($rules['on_update'] === 'RANDOM()')
					{
						if ($rules['type'] == 'varchar')
						{
							$length = ($rules['maxlength']) ? (int)$rules['maxlength'] : 255;
							$rules['on_update'] = cot_randomstring($length);
						}
						elseif ($rules['type'] == 'int')
						{
							$length = ($rules['maxlength']) ? (int)$rules['maxlength'] : 10;
							$rules['on_update'] = mt_rand(pow(10, $length-1), pow(10, $length)-1);
						}
					}
				}
				$data[$column] = $rules['on_update'];
			}
			if ($rules['type'] == 'object')
			{
				$data[$column] = serialize($data[$column]);
			}
		}
		return $data;
	}

	/**
	 * Saves object to database
	 *
	 * @param string $action Allow only a specific action, 'update' or 'insert'
	 * @return string 'update', 'insert' or false on failure
	 */
	public function save($action = null)
	{
		global $usr;
		cot_block($usr['auth_write']);
		$table = $this->tableName();
		$pk = $this->primaryKey();
		if ($this->data[$pk] && static::findByPk($this->data[$pk]))
		{
			cot_block($usr['isadmin']);
			if (!$action || $action == 'update')
			{
				$data = $this->prepData($this->data, 'update');
				if ($this->validateData($data))
				{
					$res = $this->db->update($table, $data, "$pk = ?",
						array($this->data[$pk])
					);
					if ($res !== FALSE)
					{
						return 'update';
					}
				}
			}
		}
		elseif (!$action || $action == 'insert')
		{
			$data = $this->prepData($this->data, 'insert');
			if ($this->validateData($data))
			{
				$res = $this->db->insert($table, $data);
				if ($res)
				{
					$this->data[$pk] = $this->db->lastInsertId();
					return 'insert';
				}
			}
		}
		return false;
	}

	/**
	 * Wrapper for $this->save('insert')
	 *
	 * @return bool TRUE on successful insert, FALSE otherwise
	 */
	public function insert()
	{
		return ($this->save('insert') == 'insert');
	}

	/**
	 * Wrapper for $this->save('update')
	 *
	 * @return bool TRUE on successful update, FALSE otherwise
	 */
	public function update()
	{
		return ($this->save('update') == 'update');
	}

	/**
	 * Remove object from database
	 *
	 * @param string $condition Body of WHERE clause
	 * @param array $params Array of statement input parameters, see http://www.php.net/manual/en/pdostatement.execute.php
	 * @return int Number of records removed on success or FALSE on error
	 */
	public function delete($condition, $params = array())
	{
		return $this->db->delete($this->tableName(), $condition, $params);
	}

	/**
	 * Imports column data from POST, GET or otherwise and returns them as associative array.
	 *
	 * @return array
	 */
	public static function import()
	{
		CotORM::checkPHPVersion();
		$vars = array();
		$obj = new static();
		$columns = $obj->columns(true, true);
		foreach ($columns as $name => $data)
		{
			if ($data['auto_increment']) continue;
			if ($data['on_insert'] && $data['locked']) continue;
			switch($data['type'])
			{
				case 'int':
				case 'integer':
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'bigint':
					$filter = 'INT';
					break;
				case 'float':
				case 'double':
				case 'real':
				case 'decimal':
				case 'numeric':
					$filter = 'NUM';
					break;
				default:
					$filter = ($data['alphanumeric']) ? 'ALP' : 'TXT';
					break;
			}
			$maxlen = ($data['maxlength']) ? $data['maxlength'] : 0;
			$vars[$name] = cot_import($name, $_SERVER['REQUEST_METHOD'], $filter, $maxlen);
		}
		return $vars;
	}

	/**
	 * Die if PHP version is lower than 5.3.
	 *
	 * PHP 5.3 is required for late static bindings used in finder methods.
	 * CotORM can still be used with older versions, but finder methods
	 * must be circumvented by instantiating objects within the controller
	 * instead of retrieving object instances through a finder. For example:
	 *   $obj = new MyClass();
	 *   $obj->loadData($pk);
	 * Instead of:
	 *   $obj = MyClass::findByPk($pk);
	 */
	public static function checkPHPVersion()
	{
		static $versioncompare = null;
		if ($versioncompare === null)
		{
			$versioncompare = version_compare(PHP_VERSION, '5.3.0');
		}
		if ($versioncompare == -1)
		{
			die('PHP version 5.3 or higher is required.');
		}
	}

	/**
	 * Create the table
	 *
	 * @return bool
	 */
	public static function createTable()
	{
		global $db;
		$obj = new static();
		$table = $obj->tableName();
		$pk = $obj->primaryKey();
		$cols = $obj->columns(true, true);

		$indexes = array();
		$columns = array();
		foreach ($cols as $name => $params)
		{
			$props = array();
			$params['attributes'] !== NULL && $props[] = $params['attributes'];
			$props[] = ($params['null']) ? 'NULL' : 'NOT NULL';
			$params['default_value'] !== NULL && $props[] = "DEFAULT '{$params['default_value']}'";
			$params['auto_increment'] && $props[] = 'AUTO_INCREMENT';
			$props = implode(' ', $props);

			$params['primary_key'] && $indexes[] = "PRIMARY KEY (`$name`)";
			$params['index'] && $indexes[] = "KEY `i_$name` (`$name`)";
			$params['unique'] && $indexes[] = "UNIQUE KEY `u_$name` (`$name`)";

			$type = strtoupper($params['type']);
			if ($type == 'OBJECT')
			{
				$type = 'TEXT';
			}
			if ($params['maxlength'])
			{
				$type .= "({$params['maxlength']})";
			}
			elseif ($type == 'VARCHAR')
			{
				$type .= "(255)";
			}
			$columns[] = "`$name` $type $props";
		}
		$columns = implode(', ', array_merge($columns, $indexes));

		return (bool) $db->query("
			CREATE TABLE IF NOT EXISTS `$table` ($columns)
			DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
		");
	}

	/**
	 * Drop the table
	 *
	 * @return bool
	 */
	public static function dropTable()
	{
		global $db;
		$obj = new static();
		return (bool) $db->query("
			DROP TABLE IF EXISTS `".$obj->tableName()."`
		");
	}
}

?>