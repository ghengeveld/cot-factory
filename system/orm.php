<?php

defined('COT_CODE') or die('Wrong URL.');

(function_exists('version_compare') && version_compare(PHP_VERSION, '5.3.0', '>=')) or die('PHP version 5.3 or higher is required.');

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
 * @copyright (c) Cotonti Team 2011-2012
 * @license BSD
 */
abstract class CotORM implements IteratorAggregate
{
	/**
	 * Concrete ORM class name
	 * @var string
	 */
	protected static $class_name = '';
	/**
	 * SQL table name
	 * @var string
	 */
	protected static $table_name = '';
	/**
	 * Column definitions
	 * @var array
	 */
	protected static $columns = array();
	/**
	 * Global database connection reference
	 * @var CotDB
	 */
	protected static $db = null;
	/**
	 * Object data
	 * @var array
	 */
	protected $data = array();

	/**
	 * Static constructor
	 *
	 * @global CotDB $db Database connection, for easy access in methods
	 */
	public static function __init()
	{
		global $db;
		static::$db = $db;
	}

	/**
	 * Instance constructor
	 *
	 * @param array $data Raw data
	 */
	public function __construct($data = array())
	{
		if (count($data) > 0)
		{
			foreach ($data as $column => $val)
			{
				$this->data[$column] = (static::$columns[$column]['type'] == 'object' && is_string($val)) ?
					unserialize($val) : $val;
			}
		}
		static::$class_name = get_called_class();
	}

	/**
	 * Getter for a column. Shortcut to $this->data($column).
	 * @param  string $column Column name
	 * @return mixed          Column value
	 */
	public function __get($column)
	{
		return $this->data($column);
	}

	/**
	 * isset() handler for object properties.
	 * @param  string  $column Column name
	 * @return boolean         TRUE if the column has a value, FALSE otherwise.
	 */
	public function __isset($column)
	{
		return isset($this->data[$column]);
	}

	/**
	 * Setter for a column. Shortcut to $this->data($column, $value).
	 * @param string $column Column name
	 * @param mixed  $value  Column value
	 */
	public function __set($column, $value)
	{
		return $this->data($column, $value);
	}

	/**
	 * unset() handler for object properties.
	 * @param string $column Column name
	 */
	public function __unset($column)
	{
		if (isset($this->data[$column])) unset($this->data[$column]);
	}

	/**
	 * Implements IteratorAggregate by returning an iterator for columns data.
	 * @return ArrayIterator Iterator for the data.
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->data);
	}

	/**
	 * Returns table name including prefix.
	 *
	 * @return string
	 * @global string $db_x Database table prefix
	 */
	protected static function tableName()
	{
		global $db_x;
		return $db_x.static::$table_name;
	}

	/**
	 * Returns primary key column name. Defaults to 'id' if none was set.
	 *
	 * @return string
	 */
	protected static function primaryKey()
	{
		foreach (static::$columns as $column => $info)
		{
			if ($info['primary_key']) return $column;
		}
		return 'id';
	}

	/**
	 * Getter and setter for object data
	 *
	 * @param string $column Optional column name to set data or fetch a single value
	 * @param mixed $value Optional value to set
	 * @return mixed
	 *	array of key->value pairs, or
	 *  string value if $column was provided, or
	 *  boolean if $value was provided
	 */
	public function data($column = null, $value = null)
	{
		if (!is_null($column) && !is_null($value)) // Setter
		{
			if (array_key_exists($column, static::$columns) && !static::$columns[$column]['locked'])
			{
				$this->data[$column] = $value;
				return true;
			}
			return false;
		}
		else // Getter
		{
			if (!is_null($column))
			{
				if (array_key_exists($column, static::$columns) && !static::$columns[$column]['hidden'])
				{
					return $this->data[$column];
				}
			}
			else
			{
				$data = array();
				foreach (static::$columns as $column => $val)
				{
					if ($val['hidden']) continue;
					$data[$column] = $this->data[$column];
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
	public static function columns($include_locked = false, $include_hidden = false)
	{
		$cols = array();
		foreach (static::$columns as $column => $properties)
		{
			if (!$include_hidden && $properties['hidden']) continue;
			if (!$include_locked && $properties['locked']) continue;
			$cols[$column] = $properties;
		}
		return $cols;
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
		return static::fetch($conditions, $limit, $offset, $order, $way);
	}

	/**
	 * Retrieve the first matching object
	 *
	 * @param mixed $conditions Numeric array of SQL WHERE conditions or a single
	 *  condition as a string
	 * @return CotORM Object
	 */
	public static function findOne($conditions)
	{
		$res = static::fetch($conditions, 1);
		return ($res) ? $res[0] : null;
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
		return static::fetch(array(), $limit, $offset, $order, $way);
	}

	/**
	 * Retrieve existing object from database by primary key
	 *
	 * @param mixed $pk Primary key
	 * @return object
	 */
	public static function findByPk($pk)
	{
		$res = static::fetch(static::primaryKey()." = '$pk'", 1);
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
	 * @global string $db_x Database table name prefix
	 */
	protected static function fetch($conditions = array(), $limit = 0, $offset = 0, $order = '', $way = 'DESC')
	{
		$table = static::tableName();
		$columns = array();
		$joins = array();
		foreach (array_keys(static::columns(true, true)) as $col)
		{
			$columns[] = "`$table`.`$col`";
		}
		$columns = implode(', ', $columns);
		$joins = implode(' ', $joins);

		list($where, $params) = static::parseConditions($conditions);

		$order = ($order) ? "ORDER BY `$order` $way" : '';
		$limit = ($limit) ? "LIMIT $offset, $limit" : '';

		$objects = array();
		$res = static::$db->query("
			SELECT $columns FROM $table $joins $where $order $limit
		", $params);
		while ($row = $res->fetch(PDO::FETCH_ASSOC))
		{
			$obj = new static($row);
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
	public static function count($conditions = array())
	{
		list($where, $params) = static::parseConditions($conditions);

		return (int) static::$db->query("
			SELECT COUNT(*) FROM ".static::tableName()." $where
		", $params)->fetchColumn();
	}

	/**
	 * Parses query conditions from string or array
	 *
	 * @param mixed $conditions SQL WHERE conditions as string or numeric array of strings
	 * @param array $params Optional PDO params to pass through
	 * @return array SQL WHERE part and PDO params
	 */
	protected static function parseConditions($conditions, $params = array())
	{
		$where = '';
		$table = static::tableName();
		if (!is_array($conditions)) $conditions = array($conditions);
		if (count($conditions) > 0)
		{
			$where = array();
			foreach ($conditions as $condition)
			{
				$parts = array();
				// TODO support more SQL operators
				preg_match_all('/(.+?)([<>= ]+)(.+)/', $condition, $parts);
				$column = trim($parts[1][0]);
				$operator = trim($parts[2][0]);
				$value = trim(trim($parts[3][0]), '\'"`');
				if ($column && $operator)
				{
					$where[] = "`$table`.`$column` $operator :$column";
					if ((intval($value) == $value) && (strval(intval($value)) == $value)) $value = intval($value);
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
		$pk = static::primaryKey();
		if (!$this->data[$pk]) return false;
		$res = static::$db->query("
			SELECT *
			FROM `".static::tableName()."`
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
	 * @global string $db_x Database table name prefix
	 */
	protected static function validateData($data, $for = 'insert')
	{
		global $db_x;
		if (!is_array($data))
		{
			cot_error('InvalidInput');
			return FALSE;
		}

		foreach (static::$columns as $column => $properties)
		{
			// Verify presence of required fields
			if ($properties['required'] && !$properties['nullable'] && !array_key_exists($column, $data))
			{
				cot_error(cot_rc("ColumnIsRequired", array('column' => $column)), $column);
				return FALSE;
			}
		}

		foreach ($data as $column => $value)
		{
			// Verify existence of column in model
			if (!isset(static::$columns[$column]))
			{
				cot_error(cot_rc("InvalidColumnName", array('column' => $column)), $column);
				return FALSE;
			}
			else
			{
				$properties = static::$columns[$column];
			}

			// Disallow update of primary_key
			if ($for == 'update' && $properties['primary_key'])
			{
				cot_error(cot_rc("CantUpdatePrimaryKeyColumn", array('column' => $column)), $column);
				return FALSE;
			}

			// Disallow insert/update of auto_increment columns
			if ($properties['auto_increment'])
			{
				cot_error(cot_rc("CantSetAutoIncrementColumn", array('column' => $column)), $column);
				return FALSE;
			}

			// Disallow update of locked columns
			if ($for == 'update' && $properties['locked'])
			{
				cot_error(cot_rc("CantUpdateLockedColumn", array('column' => $column)), $column);
				return FALSE;
			}

			// Check minimum length
			if (isset($properties['minlength']) && mb_strlen($value) < $properties['minlength'])
			{
				cot_error(cot_rc("ValueIsBelowMinimumLength", array(
					'column' => $column,
					'minlength' => $properties['minlength'],
					'length' => mb_strlen($value)
				)), $column);
				return FALSE;
			}

			// Check maximum length
			if (is_int($properties['maxlength']) && mb_strlen($value) > $properties['maxlength'])
			{
				cot_error(cot_rc("ValueExceedsMaximumLength", array(
					'column' => $column,
					'maxlength' => $properties['maxlength'],
					'length' => mb_strlen($value)
				)), $column);
				return FALSE;
			}

			// Verify options, but allow NULL if nullable
			if (isset($properties['options']) && !in_array($value, $properties['options']) && !(is_null($value) && $properties['nullable']))
			{
				cot_error(cot_rc('InvalidOption', array(
					'column' => $column,
					'options' => implode(', ', $properties['options'])
				)), $column);
				return FALSE;
			}

			// Run custom validators
			if (isset($properties['validators']))
			{
				if (!is_array($properties['validators']))
				{
					$properties['validators'] = array($properties['validators']);
				}
				foreach ($properties['validators'] as $validator)
				{
					if (is_callable($validator))
					{
						if (!$validator($value, $column)) return FALSE;
					}
					elseif (method_exists(static::$class_name, $validator))
					{
						if (!call_user_func(array(static::$class_name, $validator), $value, $column)) return FALSE;
					}
					elseif (is_bool($validator))
					{
						cot_error('CustomValidatorFailed', cot_rc(array(
							'column' => $column
						)), $column);
						return FALSE;
					}
					else
					{
						cot_error('InvalidValidator', cot_rc(array(
							'column' => $column
						)), $column);
						return FALSE;
					}
				}
			}

			// Check datatype
			$typecheck_pass = TRUE;
			switch ($properties['type'])
			{
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
					if (!is_int($value)) $typecheck_pass = FALSE;
					break;
				case 'int':
				case 'integer':
				case 'bigint':
					if (!is_int($value) && !is_float($value)) $typecheck_pass = FALSE;
					break;
				case 'char':
				case 'varchar':
				case 'text':
					if (!is_string($value)) $typecheck_pass = FALSE;
					break;
				case 'decimal':
				case 'numeric':
				case 'float':
				case 'double':
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

			// Check foreign key relation
			if ($properties['foreign_key'] && $properties['default_value'] !== $value)
			{
				$fk = explode(':', $properties['foreign_key']);
				if (count($fk) == 2 && static::$db->fieldExists($db_x.$fk[0], $fk[1]))
				{
					if (static::$db->query("SELECT `{$fk[1]}` FROM `$db_x{$fk[0]}` WHERE `{$fk[1]}` = ?", array($value))->rowCount() == 0)
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
	 * Prepares data for usage in db query, but doesn't validate anything or
	 * throw errors, therefore you should use validateData() as well.
	 *
	 * @param array $data Query data as column => value pairs
	 * @param string $for 'insert' or 'update'
	 * @return array Prepared query data
	 */
	protected static function prepData($data, $for)
	{
		global $sys;
		foreach (static::$columns as $column => $properties)
		{
			// Set values for empty or null columns
			if (!isset($data[$column]))
			{
				if ($for == 'insert')
				{
					if ($properties['default_value'])
					{
						$data[$column] = $properties['default_value'];
					}
					elseif ($properties['type'] == 'enum' && !$properties['nullable'])
					{
						$data[$column] = $properties['options'][0];
					}
					if (isset($properties['on_insert']))
					{
						switch ($properties['on_insert'])
						{
							case 'NOW()':
								$data[$column] = $sys['now'];
								break;

							case 'RANDOM()':
								$data[$column] = static::generateRandom($properties['type'], $properties['maxlength'], $properties['signed']);
								break;

							case 'INC()':
							case 'DEC()':
								$data[$column] = 0;
								break;

							default:
								$data[$column] = $properties['on_insert'];
								break;
						}
					}
				}
				if ($for == 'update')
				{
					// Fallback to default value if NULL is not allowed.
					if ($properties['default_value'] && !$properties['nullable'])
					{
						$data[$column] = $properties['default_value'];
					}
					if (isset($properties['on_update']))
					{
						switch ($properties['on_update'])
						{
							case 'NOW()':
								$data[$column] = $sys['now'];
								break;

							case 'RANDOM()':
								$data[$column] = static::generateRandom($properties['type'], $properties['maxlength'], $properties['signed']);
								break;

							case 'INC()':
								$data[$column] = "$column+1";
								break;

							case 'DEC()':
								$data[$column] = "$column-1";
								break;

							default:
								$data[$column] = $properties['on_update'];
								break;
						}
					}
				}
			}
			// Serialize objects before storing in database
			if ($properties['type'] == 'object')
			{
				$data[$column] = serialize($data[$column]);
			}
			// Skip primary keys, auto_increment and locked fields on update
			if ($for == 'update' && ($properties['auto_increment'] || $properties['primary_key'] || $properties['locked']))
			{
				unset($data[$column]);
			}
		}
		return $data;
	}

	/**
	 * Generates a random value
	 *
	 * @param string $datatype MySQL data type
	 * @param mixed $maxlength Maximum display length (int) for integers and
	 *  strings, or a string representing precision and scale for floating-point
	 *  and fixed-point numeric types.
	 * @param bool $signed Allow negative numbers
	 * @return mixed Random number or string
	 */
	protected static function generateRandom($datatype, $maxlength = null, $signed = false)
	{
		switch ($datatype)
		{
			case 'int':
			case 'integer':
			case 'tinyint':
			case 'smallint':
			case 'mediumint':
			case 'bigint':
				$length = $maxlength ? (int)$maxlength : 10;
				$negation = $signed ? round(mt_rand(0, 1)) ? 1 : -1 : 1;
				return mt_rand(pow(10, $length-1), pow(10, $length)-1) * $negation;
			case 'float':
			case 'double':
			case 'real':
			case 'decimal':
			case 'numeric':
				list($precision, $scale) = explode(',', $maxlength);
				$negation = $signed ? round(mt_rand(0, 1)) ? 1 : -1 : 1;
				return mt_rand(pow(10, $precision-1), pow(10, $precision)-1) / pow(10, $scale) * $negation;
			default:
				$length = $maxlength ? (int)$maxlength : 255;
				return cot_randomstring($length);
		}
	}

	/**
	 * Saves object to database
	 *
	 * @param string $action Allow only a specific action, 'update' or 'insert'
	 * @return string 'update', 'insert' or false on failure
	 */
	public function save($action = null)
	{
		$table = static::tableName();
		$pk = static::primaryKey();
		if ($this->data[$pk] && static::findByPk($this->data[$pk]))
		{
			if (!$action || $action == 'update')
			{
				$data = static::prepData($this->data, 'update');
				if (static::validateData($data, 'update'))
				{
					$res = static::$db->update($table, $data, "$pk = ?",
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
			$data = static::prepData($this->data, 'insert');
			if (static::validateData($data, 'insert'))
			{
				$res = static::$db->insert($table, $data);
				if ($res)
				{
					$this->data[$pk] = static::$db->lastInsertId();
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
	public static function delete($condition, $params = array())
	{
		return static::$db->delete(static::tableName(), $condition, $params);
	}

	/**
	 * Imports column data from POST, GET or otherwise and returns them as associative array.
	 *
	 * @param string $method Custom request method. Current $_SERVER['REQUEST_METHOD'] is used by default.
	 * @return CotORM
	 */
	public static function import($method = '')
	{
		$vars = array();
		$columns = static::columns(true, true);
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
			if (empty($method)) $method = $_SERVER['REQUEST_METHOD'];
			$vars[$name] = cot_import($name, $method, $filter, $maxlen);
		}
		return new static($vars);
	}

	/**
	 * Create the table
	 *
	 * @return bool
	 */
	public static function createTable()
	{
		$table = static::tableName();
		$columns = static::columns(true, true);

		$query_indexes = array();
		$query_columns = array();
		foreach ($columns as $column => $properties)
		{
			$props = array();
			$type = strtoupper($properties['type']);
			if (in_array($type, array('INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'FLOAT', 'DOUBLE', 'REAL', 'DECIMAL', 'NUMERIC')))
			{
				$props[] = ($properties['signed']) ? 'SIGNED' : 'UNSIGNED';
			}
			$props[] = ($properties['nullable']) ? 'NULL' : 'NOT NULL';
			$properties['default_value'] !== NULL && $props[] = "DEFAULT '{$properties['default_value']}'";
			$properties['auto_increment'] && $props[] = 'AUTO_INCREMENT';

			$properties['primary_key'] && $query_indexes[] = "PRIMARY KEY (`$column`)";
			$properties['index'] && $query_indexes[] = "KEY `i_$column` (`$column`)";
			$properties['unique'] && $query_indexes[] = "UNIQUE KEY `u_$column` (`$column`)";

			if ($type == 'OBJECT')
			{
				$type = 'TEXT';
			}
			elseif ($type == 'ENUM')
			{
				$type .= "('". implode("', '", $properties['options']) ."')";
			}
			elseif ($properties['maxlength'])
			{
				$type .= "({$properties['maxlength']})";
			}
			elseif ($type == 'VARCHAR')
			{
				$type .= "(255)";
			}
			$props = implode(' ', $props);
			$query_columns[] = "`$column` $type $props";
		}
		$query_columns = implode(', ', array_merge($query_columns, $query_indexes));

		return (bool) static::$db->query("
			CREATE TABLE IF NOT EXISTS `$table` ($query_columns)
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
		return (bool) static::$db->query("
			DROP TABLE IF EXISTS `".static::tableName()."`
		");
	}

	/**
	 * Seed the table with data
	 *
	 * @param array $rowdata Numeric array representing table rows.
	 *  Each item should be a numeric array with column data.
	 * @param array $columnnames Numeric array of column names. [optional]
	 */
	public static function seed($rowdata, $columnnames = array())
	{
		if ($rowdata && is_array($rowdata) && (!$columnnames || count($columnnames) == count($rowdata[0])))
		{
			$chunks = array_chunk($rowdata, 100);
			foreach ($chunks as $chunk)
			{
				foreach ($chunk as &$row)
				{
					foreach ($row as &$value)
					{
						$value = mysql_real_escape_string($value);
					}
					$row = "'" . implode("', '", $row) . "'";
				}
				$values = '(' . implode('), (', $chunk) . ')';
				$keys = ($columnnames) ? "(`" . implode("`,`", $columnnames) . "`)" : '';

				static::$db->query("INSERT IGNORE INTO `".static::tableName()."` $keys VALUES $values");
			}
		}
	}
}

// Class initialization for some static variables
CotORM::__init();
