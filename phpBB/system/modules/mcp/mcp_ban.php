<?php
/**
*
* @package mcp
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
* @package mcp
*/
class mcp_ban
{
	var $u_action;

	function main($id, $mode)
	{



		include(phpbb::$phpbb_root_path . 'system/includes/functions_user.' . phpbb::$phpEx);

		// Include the admin banning interface...
		include(phpbb::$phpbb_root_path . '/system/modules/acp/acp_ban.' . phpbb::$phpEx);

		$bansubmit		= (isset($_POST['bansubmit'])) ? true : false;
		$unbansubmit	= (isset($_POST['unbansubmit'])) ? true : false;
		$current_time	= time();

		phpbb::$user->add_lang(array('acp/ban', 'acp/users'));
		$this->tpl_name = 'mcp_ban';

		// Ban submitted?
		if ($bansubmit)
		{
			// Grab the list of entries
			$ban				= request_var('ban', '', ($mode === 'user') ? true : false);

			if ($mode === 'user')
			{
				$ban = utf8_normalize_nfc($ban);
			}

			$ban_len			= request_var('banlength', 0);
			$ban_len_other		= request_var('banlengthother', '');
			$ban_exclude		= request_var('banexclude', 0);
			$ban_reason			= utf8_normalize_nfc(request_var('banreason', '', true));
			$ban_give_reason	= utf8_normalize_nfc(request_var('bangivereason', '', true));

			if ($ban)
			{
				if (confirm_box(true))
				{
					user_ban($mode, $ban, $ban_len, $ban_len_other, $ban_exclude, $ban_reason, $ban_give_reason);

					trigger_error(phpbb::$user->lang['BAN_UPDATE_SUCCESSFUL'] . '<br /><br /><a href="' . $this->u_action . '">&laquo; ' . phpbb::$user->lang['BACK_TO_PREV'] . '</a>');
				}
				else
				{
					confirm_box(false, phpbb::$user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
						'mode'				=> $mode,
						'ban'				=> $ban,
						'bansubmit'			=> true,
						'banlength'			=> $ban_len,
						'banlengthother'	=> $ban_len_other,
						'banexclude'		=> $ban_exclude,
						'banreason'			=> $ban_reason,
						'bangivereason'		=> $ban_give_reason)));
				}
			}
		}
		else if ($unbansubmit)
		{
			$ban = request_var('unban', array(''));

			if ($ban)
			{
				if (confirm_box(true))
				{
					user_unban($mode, $ban);

					trigger_error(phpbb::$user->lang['BAN_UPDATE_SUCCESSFUL'] . '<br /><br /><a href="' . $this->u_action . '">&laquo; ' . phpbb::$user->lang['BACK_TO_PREV'] . '</a>');
				}
				else
				{
					confirm_box(false, phpbb::$user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
						'mode'			=> $mode,
						'unbansubmit'	=> true,
						'unban'			=> $ban)));
				}
			}
		}

		// Ban length options
		$ban_end_text = array(0 => phpbb::$user->lang['PERMANENT'], 30 => phpbb::$user->lang['30_MINS'], 60 => phpbb::$user->lang['1_HOUR'], 360 => phpbb::$user->lang['6_HOURS'], 1440 => phpbb::$user->lang['1_DAY'], 10080 => phpbb::$user->lang['7_DAYS'], 20160 => phpbb::$user->lang['2_WEEKS'], 40320 => phpbb::$user->lang['1_MONTH'], -1 => phpbb::$user->lang['UNTIL'] . ' -&gt; ');

		$ban_end_options = '';
		foreach ($ban_end_text as $length => $text)
		{
			$ban_end_options .= '<option value="' . $length . '">' . $text . '</option>';
		}

		// Define language vars
		$this->page_title = phpbb::$user->lang[strtoupper($mode) . '_BAN'];

		$l_ban_explain = phpbb::$user->lang[strtoupper($mode) . '_BAN_EXPLAIN'];
		$l_ban_exclude_explain = phpbb::$user->lang[strtoupper($mode) . '_BAN_EXCLUDE_EXPLAIN'];
		$l_unban_title = phpbb::$user->lang[strtoupper($mode) . '_UNBAN'];
		$l_unban_explain = phpbb::$user->lang[strtoupper($mode) . '_UNBAN_EXPLAIN'];
		$l_no_ban_cell = phpbb::$user->lang[strtoupper($mode) . '_NO_BANNED'];

		switch ($mode)
		{
			case 'user':
				$l_ban_cell = phpbb::$user->lang['USERNAME'];
			break;

			case 'ip':
				$l_ban_cell = phpbb::$user->lang['IP_HOSTNAME'];
			break;

			case 'email':
				$l_ban_cell = phpbb::$user->lang['EMAIL_ADDRESS'];
			break;
		}

		acp_ban::display_ban_options($mode);

		phpbb::$template->assign_vars(array(
			'L_TITLE'				=> $this->page_title,
			'L_EXPLAIN'				=> $l_ban_explain,
			'L_UNBAN_TITLE'			=> $l_unban_title,
			'L_UNBAN_EXPLAIN'		=> $l_unban_explain,
			'L_BAN_CELL'			=> $l_ban_cell,
			'L_BAN_EXCLUDE_EXPLAIN'	=> $l_ban_exclude_explain,
			'L_NO_BAN_CELL'			=> $l_no_ban_cell,

			'S_USERNAME_BAN'	=> ($mode == 'user') ? true : false,

			'U_ACTION'			=> $this->u_action,
			'U_FIND_USERNAME'	=> append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=searchuser&amp;form=mcp_ban&amp;field=ban'),
		));

		if ($mode === 'email' && !phpbb::$auth->acl_get('a_user'))
		{
			return;
		}

		// As a "service" we will check if any post id is specified and populate the username of the poster id if given
		$post_id = request_var('p', 0);
		$user_id = request_var('u', 0);
		$username = $pre_fill = false;

		if ($user_id && $user_id <> ANONYMOUS)
		{
			$sql = 'SELECT username, user_email, user_ip
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' . $user_id;
			$result = phpbb::$db->sql_query($sql);
			switch ($mode)
			{
				case 'user':
					$pre_fill = (string) phpbb::$db->sql_fetchfield('username');
				break;
				
				case 'ip':
					$pre_fill = (string) phpbb::$db->sql_fetchfield('user_ip');
				break;

				case 'email':
					$pre_fill = (string) phpbb::$db->sql_fetchfield('user_email');
				break;
			}
			phpbb::$db->sql_freeresult($result);
		}
		else if ($post_id)
		{
			$post_info = get_post_data($post_id, 'm_ban');

			if (sizeof($post_info) && !empty($post_info[$post_id]))
			{
				switch ($mode)
				{
					case 'user':
						$pre_fill = $post_info[$post_id]['username'];
					break;

					case 'ip':
						$pre_fill = $post_info[$post_id]['poster_ip'];
					break;

					case 'email':
						$pre_fill = $post_info[$post_id]['user_email'];
					break;
				}

			}
		}

		if ($pre_fill)
		{
			// left for legacy template compatibility
			phpbb::$template->assign_var('USERNAMES', $pre_fill);
			phpbb::$template->assign_var('BAN_QUANTIFIER', $pre_fill);
		}
	}
}

?>