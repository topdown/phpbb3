<?php

/**
 * The future of removing globals.
 *
 *
 * Long description for file (if any)...
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Created 8/20/11, 12:14 AM
 *
 * @category   $CategoryName
 * @package    $Package - phpbb.php
 * @author     Jeff Behnke <code@valid-webs.com>
 * @copyright  2009-11 Valid-Webs.com
 * @license    $License
 * @version    $Version
 */


/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	//exit;
}


/**
 * Class phpbb
 */
class phpbb
{

	/**
	 * @var $auth auth
	 */
	public static $auth;

	/**
	 * @var $cache acm
	 */
	public static $cache;

	/**
	 * @var $config
	 */
	public static $config;

	/**
	 * @var $db dbal_mysql
	 */
	public static $db;

	/**
	 * @var $template Template
	 */
	public static $template;

	/**
	 * @var $user User
	 */
	public static $user;

	public static $phpEx;
	public static $phpbb_root_path;

	/**
	 * @static
	 * @param $acm_type
	 * @param $dbms
	 * @return void
	 */
	public static function instance($acm_type, $dbms)
	{
		global $phpbb_root_path;

		self::$phpbb_root_path = &$phpbb_root_path;
		
		$phpEx = 'php';

		require($phpbb_root_path . 'system/acm/acm_' . $acm_type . '.' . $phpEx);
		require($phpbb_root_path . 'system/core/cache.' . $phpEx);
		require($phpbb_root_path . 'system/core/template.' . $phpEx);
		require($phpbb_root_path . 'system/core/session.' . $phpEx);
		require($phpbb_root_path . 'system/core/auth.' . $phpEx);
		require($phpbb_root_path . 'system/db/' . $dbms . '.' . $phpEx);
		
		switch($dbms)
		{
			case 'mysqli' :

				$db			= new dbal_mysqli();

			break;

			case 'mysql' :

				$db			= new dbal_mysql();

			break;

			case 'mssql' :

				$db			= new dbal_mssql();

			break;

			case 'oracle' :

				$db			= new dbal_oracle();

			break;

			case 'firebird' :

				$db			= new dbal_firebird();

			break;

			case 'postgres' :

				$db			= new dbal_postgres();

			break;

			case 'sqlite' :

				$db			= new dbal_sqlite();

			break;

			case 'mssql_odbc' :

				$db			= new dbal_mssql_odbc();

			break;

			case 'mssqlnative' :

				$db			= new dbal_mssqlnative();

			break;
		}


		/** @var $user User */
		$user		= new user();

		/** @var $auth Auth */
		$auth		= new auth();

		/** @var $template Template */
		$template	= new template();

		/** @var $cache cache */
		$cache		= new cache();

		self::$auth = &$auth;
		self::$config = &$config;
		self::$db = &$db;
		self::$template = &$template;
		self::$user = &$user;
		self::$cache = &$cache;
		self::$phpEx = &$phpEx;

	}
}