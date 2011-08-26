<?php
/**
*
* @package acp
* @version $Id$
* @copyright (c) 2006 phpBB Group
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
class acp_inactive
{
	var $u_action;
	var $p_master;

	function acp_inactive(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	function main($id, $mode)
	{

		global $phpbb_admin_path, $table_prefix;

		include(phpbb::$phpbb_root_path . 'system/includes/functions_user.' . phpbb::$phpEx);

		phpbb::$user->add_lang('memberlist');

		$action = request_var('action', '');
		$mark	= (isset($_REQUEST['mark'])) ? request_var('mark', array(0)) : array();
		$start	= request_var('start', 0);
		$submit = isset($_POST['submit']);

		// Sort keys
		$sort_days	= request_var('st', 0);
		$sort_key	= request_var('sk', 'i');
		$sort_dir	= request_var('sd', 'd');

		$form_key = 'acp_inactive';
		add_form_key($form_key);

		// We build the sort key and per page settings here, because they may be needed later

		// Number of entries to display
		$per_page = request_var('users_per_page', (int) phpbb::$config['topics_per_page']);

		// Sorting
		$limit_days = array(0 => phpbb::$user->lang['ALL_ENTRIES'], 1 => phpbb::$user->lang['1_DAY'], 7 => phpbb::$user->lang['7_DAYS'], 14 => phpbb::$user->lang['2_WEEKS'], 30 => phpbb::$user->lang['1_MONTH'], 90 => phpbb::$user->lang['3_MONTHS'], 180 => phpbb::$user->lang['6_MONTHS'], 365 => phpbb::$user->lang['1_YEAR']);
		$sort_by_text = array('i' => phpbb::$user->lang['SORT_INACTIVE'], 'j' => phpbb::$user->lang['SORT_REG_DATE'], 'l' => phpbb::$user->lang['SORT_LAST_VISIT'], 'd' => phpbb::$user->lang['SORT_LAST_REMINDER'], 'r' => phpbb::$user->lang['SORT_REASON'], 'u' => phpbb::$user->lang['SORT_USERNAME'], 'p' => phpbb::$user->lang['SORT_POSTS'], 'e' => phpbb::$user->lang['SORT_REMINDER']);
		$sort_by_sql = array('i' => 'user_inactive_time', 'j' => 'user_regdate', 'l' => 'user_lastvisit', 'd' => 'user_reminded_time', 'r' => 'user_inactive_reason', 'u' => 'username_clean', 'p' => 'user_posts', 'e' => 'user_reminded');

		$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';
		gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param);

		if ($submit && sizeof($mark))
		{
			if ($action !== 'delete' && !check_form_key($form_key))
			{
				trigger_error(phpbb::$user->lang['FORM_INVALID'] . adm_back_link($this->u_action), E_USER_WARNING);
			}

			switch ($action)
			{
				case 'activate':
				case 'delete':

					$sql = 'SELECT user_id, username
						FROM ' . USERS_TABLE . '
						WHERE ' . phpbb::$db->sql_in_set('user_id', $mark);
					$result = phpbb::$db->sql_query($sql);

					$user_affected = array();
					while ($row = phpbb::$db->sql_fetchrow($result))
					{
						$user_affected[$row['user_id']] = $row['username'];
					}
					phpbb::$db->sql_freeresult($result);

					if ($action == 'activate')
					{
						// Get those 'being activated'...
						$sql = 'SELECT user_id, username' . ((phpbb::$config['require_activation'] == USER_ACTIVATION_ADMIN) ? ', user_email, user_lang' : '') . '
							FROM ' . USERS_TABLE . '
							WHERE ' . phpbb::$db->sql_in_set('user_id', $mark) . '
								AND user_type = ' . USER_INACTIVE;
						$result = phpbb::$db->sql_query($sql);

						$inactive_users = array();
						while ($row = phpbb::$db->sql_fetchrow($result))
						{
							$inactive_users[] = $row;
						}
						phpbb::$db->sql_freeresult($result);

						user_active_flip('activate', $mark);

						if (phpbb::$config['require_activation'] == USER_ACTIVATION_ADMIN && !empty($inactive_users))
						{
							include_once(phpbb::$phpbb_root_path . 'system/includes/functions_messenger.' . phpbb::$phpEx);

							$messenger = new messenger(false);

							foreach ($inactive_users as $row)
							{
								$messenger->template('admin_welcome_activated', $row['user_lang']);

								$messenger->to($row['user_email'], $row['username']);

								$messenger->headers('X-AntiAbuse: Board servername - ' . phpbb::$config['server_name']);
								$messenger->headers('X-AntiAbuse: User_id - ' . phpbb::$user->data['user_id']);
								$messenger->headers('X-AntiAbuse: Username - ' . phpbb::$user->data['username']);
								$messenger->headers('X-AntiAbuse: User IP - ' . phpbb::$user->ip);

								$messenger->assign_vars(array(
									'USERNAME'	=> htmlspecialchars_decode($row['username']))
								);

								$messenger->send(NOTIFY_EMAIL);
							}

							$messenger->save_queue();
						}

						if (!empty($inactive_users))
						{
							foreach ($inactive_users as $row)
							{
								add_log('admin', 'LOG_USER_ACTIVE', $row['username']);
								add_log('user', $row['user_id'], 'LOG_USER_ACTIVE_USER');
							}
						}

						// For activate we really need to redirect, else a refresh can result in users being deactivated again
						$u_action = $this->u_action . "&amp;$u_sort_param&amp;start=$start";
						$u_action .= ($per_page != phpbb::$config['topics_per_page']) ? "&amp;users_per_page=$per_page" : '';

						redirect($u_action);
					}
					else if ($action == 'delete')
					{
						if (confirm_box(true))
						{
							if (!phpbb::$auth->acl_get('a_userdel'))
							{
								trigger_error(phpbb::$user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
							}

							foreach ($mark as $user_id)
							{
								user_delete('retain', $user_id, $user_affected[$user_id]);
							}

							add_log('admin', 'LOG_INACTIVE_' . strtoupper($action), implode(', ', $user_affected));
						}
						else
						{
							$s_hidden_fields = array(
								'mode'			=> $mode,
								'action'		=> $action,
								'mark'			=> $mark,
								'submit'		=> 1,
								'start'			=> $start,
							);
							confirm_box(false, phpbb::$user->lang['CONFIRM_OPERATION'], build_hidden_fields($s_hidden_fields));
						}
					}

				break;

				case 'remind':
					if (empty(phpbb::$config['email_enable']))
					{
						trigger_error(phpbb::$user->lang['EMAIL_DISABLED'] . adm_back_link($this->u_action), E_USER_WARNING);
					}

					$sql = 'SELECT user_id, username, user_email, user_lang, user_jabber, user_notify_type, user_regdate, user_actkey
						FROM ' . USERS_TABLE . '
						WHERE ' . phpbb::$db->sql_in_set('user_id', $mark) . '
							AND user_inactive_reason';

					$sql .= (phpbb::$config['require_activation'] == USER_ACTIVATION_ADMIN) ? ' = ' . INACTIVE_REMIND : ' <> ' . INACTIVE_MANUAL;

					$result = phpbb::$db->sql_query($sql);

					if ($row = phpbb::$db->sql_fetchrow($result))
					{
						// Send the messages
						include_once(phpbb::$phpbb_root_path . 'system/includes/functions_messenger.' . phpbb::$phpEx);

						$messenger = new messenger();
						$usernames = $user_ids = array();

						do
						{
							$messenger->template('user_remind_inactive', $row['user_lang']);

							$messenger->to($row['user_email'], $row['username']);
							$messenger->im($row['user_jabber'], $row['username']);

							$messenger->headers('X-AntiAbuse: Board servername - ' . phpbb::$config['server_name']);
							$messenger->headers('X-AntiAbuse: User_id - ' . phpbb::$user->data['user_id']);
							$messenger->headers('X-AntiAbuse: Username - ' . phpbb::$user->data['username']);
							$messenger->headers('X-AntiAbuse: User IP - ' . phpbb::$user->ip);

							$messenger->assign_vars(array(
								'USERNAME'		=> htmlspecialchars_decode($row['username']),
								'REGISTER_DATE'	=> phpbb::$user->format_date($row['user_regdate'], false, true),
								'U_ACTIVATE'	=> generate_board_url() . "/ucp.$phpEx?mode=activate&u=" . $row['user_id'] . '&k=' . $row['user_actkey'])
							);

							$messenger->send($row['user_notify_type']);

							$usernames[] = $row['username'];
							$user_ids[] = (int) $row['user_id'];
						}
						while ($row = phpbb::$db->sql_fetchrow($result));

						$messenger->save_queue();

						// Add the remind state to the database
						$sql = 'UPDATE ' . USERS_TABLE . '
							SET user_reminded = user_reminded + 1,
								user_reminded_time = ' . time() . '
							WHERE ' . phpbb::$db->sql_in_set('user_id', $user_ids);
						phpbb::$db->sql_query($sql);

						add_log('admin', 'LOG_INACTIVE_REMIND', implode(', ', $usernames));
						unset($usernames);
					}
					phpbb::$db->sql_freeresult($result);

					// For remind we really need to redirect, else a refresh can result in more than one reminder
					$u_action = $this->u_action . "&amp;$u_sort_param&amp;start=$start";
					$u_action .= ($per_page != phpbb::$config['topics_per_page']) ? "&amp;users_per_page=$per_page" : '';

					redirect($u_action);

				break;
			}
		}

		// Define where and sort sql for use in displaying logs
		$sql_where = ($sort_days) ? (time() - ($sort_days * 86400)) : 0;
		$sql_sort = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');

		$inactive = array();
		$inactive_count = 0;

		$start = view_inactive_users($inactive, $inactive_count, $per_page, $start, $sql_where, $sql_sort);

		foreach ($inactive as $row)
		{
			phpbb::$template->assign_block_vars('inactive', array(
				'INACTIVE_DATE'	=> phpbb::$user->format_date($row['user_inactive_time']),
				'REMINDED_DATE'	=> phpbb::$user->format_date($row['user_reminded_time']),
				'JOINED'		=> phpbb::$user->format_date($row['user_regdate']),
				'LAST_VISIT'	=> (!$row['user_lastvisit']) ? ' - ' : phpbb::$user->format_date($row['user_lastvisit']),

				'REASON'		=> $row['inactive_reason'],
				'USER_ID'		=> $row['user_id'],
				'POSTS'			=> ($row['user_posts']) ? $row['user_posts'] : 0,
				'REMINDED'		=> $row['user_reminded'],

				'REMINDED_EXPLAIN'	=> phpbb::$user->lang('USER_LAST_REMINDED', (int) $row['user_reminded'], phpbb::$user->format_date($row['user_reminded_time'])),

				'USERNAME_FULL'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'], false, append_sid("{$phpbb_admin_path}index." . phpbb::$phpEx, 'i=users&amp;mode=overview')),
				'USERNAME'			=> get_username_string('username', $row['user_id'], $row['username'], $row['user_colour']),
				'USER_COLOR'		=> get_username_string('colour', $row['user_id'], $row['username'], $row['user_colour']),

				'U_USER_ADMIN'	=> append_sid("{$phpbb_admin_path}index." . phpbb::$phpEx, "i=users&amp;mode=overview&amp;u={$row['user_id']}"),
				'U_SEARCH_USER'	=> (phpbb::$auth->acl_get('u_search')) ? append_sid(phpbb::$phpbb_root_path . "search." . phpbb::$phpEx, "author_id={$row['user_id']}&amp;sr=posts") : '',
			));
		}

		$option_ary = array('activate' => 'ACTIVATE', 'delete' => 'DELETE');
		if (phpbb::$config['email_enable'])
		{
			$option_ary += array('remind' => 'REMIND');
		}

		phpbb::$template->assign_vars(array(
			'S_INACTIVE_USERS'		=> true,
			'S_INACTIVE_OPTIONS'	=> build_select($option_ary),

			'S_LIMIT_DAYS'	=> $s_limit_days,
			'S_SORT_KEY'	=> $s_sort_key,
			'S_SORT_DIR'	=> $s_sort_dir,
			'S_ON_PAGE'		=> on_page($inactive_count, $per_page, $start),
			'PAGINATION'	=> generate_pagination($this->u_action . "&amp;$u_sort_param&amp;users_per_page=$per_page", $inactive_count, $per_page, $start, true),
			'USERS_PER_PAGE'	=> $per_page,

			'U_ACTION'		=> $this->u_action . '&amp;start=' . $start,
		));

		$this->tpl_name = 'acp_inactive';
		$this->page_title = 'ACP_INACTIVE_USERS';
	}
}

?>