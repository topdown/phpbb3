<?php
/**
*
* @package VC
* @version $Id$
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* A small class for 3.0.x (no autoloader in 3.0.x)
*
* @package VC
*/
class phpbb_captcha_factory
{
	/**
	 * return an instance of class $name in file $name_plugin.php
	 *
	 * @param $name
	 * @return call_user_func $instance
	 */
	public static function &get_instance($name)
	{
		$name = basename($name);
		if (!class_exists($name))
		{
			include(phpbb::$phpbb_root_path . "system/captcha/plugins/{$name}_plugin." . phpbb::$phpEx);
		}
		$instance = call_user_func(array($name, 'get_instance'));
		return $instance;
	}

	/**
	 * Call the garbage collector
	 *
	 * @param $name
	 */
	public static function garbage_collect($name)
	{
		$name = basename($name);
		if (!class_exists($name))
		{
			include(phpbb::$phpbb_root_path . "system/captcha/plugins/{$name}_plugin." . phpbb::$phpEx);
		}
		call_user_func(array($name, 'garbage_collect'), 0);
	}

	/**
	* return a list of all discovered CAPTCHA plugins
	*/
	function get_captcha_types()
	{
		$captchas = array(
			'available'		=> array(),
			'unavailable'	=> array(),
		);

		$dp = @opendir(phpbb::$phpbb_root_path . 'system/captcha/plugins');

		if ($dp)
		{
			while (($file = readdir($dp)) !== false)
			{
				if ((preg_match('#_plugin\.' . $phpEx . '$#', $file)))
				{
					$name = preg_replace('#^(.*?)_plugin\.' . $phpEx . '$#', '\1', $file);
					if (!class_exists($name))
					{
						include(phpbb::$phpbb_root_path . "system/captcha/plugins/$file");
					}

					if (call_user_func(array($name, 'is_available')))
					{
						$captchas['available'][$name] = call_user_func(array($name, 'get_name'));
					}
					else
					{
						$captchas['unavailable'][$name] = call_user_func(array($name, 'get_name'));
					}
				}
			}
			closedir($dp);
		}

		return $captchas;
	}
}
