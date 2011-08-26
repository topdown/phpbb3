<?php
/**
*
* @package acp
* @version $Id$
* @copyright (c) 2005 phpBB Group
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
* @package acp
*/
class acp_disallow
{
	var $u_action;

	function main($id, $mode)
	{
		global $phpbb_admin_path;

		include(phpbb::$phpbb_root_path . 'system/includes/functions_user.' . phpbb::$phpEx);

		phpbb::$user->add_lang('acp/posting');

		// Set up general vars
		$this->tpl_name = 'acp_disallow';
		$this->page_title = 'ACP_DISALLOW_USERNAMES';

		$form_key = 'acp_disallow';
		add_form_key($form_key);

		$disallow = (isset($_POST['disallow'])) ? true : false;
		$allow = (isset($_POST['allow'])) ? true : false;

		if (($allow || $disallow) && !check_form_key($form_key))
		{
			trigger_error(phpbb::$user->lang['FORM_INVALID'] . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if ($disallow)
		{
			$disallowed_user = str_replace('*', '%', utf8_normalize_nfc(request_var('disallowed_user', '', true)));

			if (!$disallowed_user)
			{
				trigger_error(phpbb::$user->lang['NO_USERNAME_SPECIFIED'] . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$sql = 'SELECT disallow_id
				FROM ' . DISALLOW_TABLE . "
				WHERE disallow_username = '" . phpbb::$db->sql_escape($disallowed_user) . "'";
			$result = phpbb::$db->sql_query($sql);
			$row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			if ($row)
			{
				trigger_error(phpbb::$user->lang['DISALLOWED_ALREADY'] . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$sql = 'INSERT INTO ' . DISALLOW_TABLE . ' ' . phpbb::$db->sql_build_array('INSERT', array('disallow_username' => $disallowed_user));
			phpbb::$db->sql_query($sql);

			phpbb::$cache->destroy('_disallowed_usernames');

			$message = phpbb::$user->lang['DISALLOW_SUCCESSFUL'];
			add_log('admin', 'LOG_DISALLOW_ADD', str_replace('%', '*', $disallowed_user));

			trigger_error($message . adm_back_link($this->u_action));
		}
		else if ($allow)
		{
			$disallowed_id = request_var('disallowed_id', 0);

			if (!$disallowed_id)
			{
				trigger_error(phpbb::$user->lang['NO_USERNAME_SPECIFIED'] . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$sql = 'DELETE FROM ' . DISALLOW_TABLE . '
				WHERE disallow_id = ' . $disallowed_id;
			phpbb::$db->sql_query($sql);

			phpbb::$cache->destroy('_disallowed_usernames');

			add_log('admin', 'LOG_DISALLOW_DELETE');

			trigger_error(phpbb::$user->lang['DISALLOWED_DELETED'] . adm_back_link($this->u_action));
		}

		// Grab the current list of disallowed usernames...
		$sql = 'SELECT *
			FROM ' . DISALLOW_TABLE;
		$result = phpbb::$db->sql_query($sql);

		$disallow_select = '';
		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$disallow_select .= '<option value="' . $row['disallow_id'] . '">' . str_replace('%', '*', $row['disallow_username']) . '</option>';
		}
		phpbb::$db->sql_freeresult($result);

		phpbb::$template->assign_vars(array(
			'U_ACTION'				=> $this->u_action,
			'S_DISALLOWED_NAMES'	=> $disallow_select)
		);
	}
}

?>