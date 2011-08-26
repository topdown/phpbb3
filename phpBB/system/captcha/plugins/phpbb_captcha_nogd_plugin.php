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
class phpbb_captcha_nogd extends phpbb_default_captcha
{

	function phpbb_captcha_nogd()
	{


		if (!class_exists('captcha'))
		{
			include_once(phpbb::$phpbb_root_path . 'system/captcha/captcha_non_gd.' . phpbb::$phpEx);
		}
	}

	function &get_instance()
	{
		$instance =& new phpbb_captcha_nogd();
		return $instance;
	}

	function is_available()
	{
		return true;
	}

	function get_name()
	{
		return 'CAPTCHA_NO_GD';
	}

	function get_class_name()
	{
		return 'phpbb_captcha_nogd';
	}

	function acp_page($id, &$module)
	{


		trigger_error(phpbb::$user->lang['CAPTCHA_NO_OPTIONS'] . adm_back_link($module->u_action));
	}
}

?>