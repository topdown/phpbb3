<?php
/**
*
* @package VC
* @version $Id$
* @copyright (c) 2006, 2008 phpBB Group
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
* Placeholder for autoload
*/
if (!class_exists('phpbb_default_captcha'))
{
	include(phpbb::$phpbb_root_path . 'system/captcha/plugins/captcha_abstract.' . phpbb::$phpEx);
}

/**
* @package VC
*/
class phpbb_captcha_gd_wave extends phpbb_default_captcha
{

	function phpbb_captcha_gd_wave()
	{


		if (!class_exists('captcha'))
		{
			include_once(phpbb::$phpbb_root_path . 'system/captcha/captcha_gd_wave.' . phpbb::$phpEx);
		}
	}

	function get_instance()
	{
		return new phpbb_captcha_gd_wave();
	}

	function is_available()
	{


		if (@extension_loaded('gd'))
		{
			return true;
		}

		if (!function_exists('can_load_dll'))
		{
			include(phpbb::$phpbb_root_path . 'system/includes/functions_install.' . phpbb::$phpEx);
		}

		return can_load_dll('gd');
	}

	function get_name()
	{
		return 'CAPTCHA_GD_3D';
	}

	function get_class_name()
	{
		return 'phpbb_captcha_gd_wave';
	}

	function acp_page($id, &$module)
	{

		trigger_error(phpbb::$user->lang['CAPTCHA_NO_OPTIONS'] . adm_back_link($module->u_action));
	}
}

?>