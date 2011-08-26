<?php
/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'system/common.' . $phpEx);
include(phpbb::$phpbb_root_path . 'system/includes/functions_display.' . phpbb::$phpEx);

// Start session management
phpbb::$user->session_begin();
phpbb::$auth->acl(phpbb::$user->data);
phpbb::$user->setup(array('memberlist', 'groups'));

// Grab data
$mode		= request_var('mode', '');
$action		= request_var('action', '');
$user_id	= request_var('u', ANONYMOUS);
$username	= request_var('un', '', true);
$group_id	= request_var('g', 0);
$topic_id	= request_var('t', 0);

// Check our mode...
if (!in_array($mode, array('', 'group', 'viewprofile', 'email', 'contact', 'searchuser', 'leaders')))
{
	trigger_error('NO_MODE');
}

switch ($mode)
{
	case 'email':
	break;

	default:
		// Can this user view profiles/memberlist?
		if (!phpbb::$auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel'))
		{
			if (phpbb::$user->data['user_id'] != ANONYMOUS)
			{
				trigger_error('NO_VIEW_USERS');
			}

			login_box('', ((isset(phpbb::$user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)])) ? phpbb::$user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)] : phpbb::$user->lang['LOGIN_EXPLAIN_MEMBERLIST']));
		}
	break;
}

$start	= request_var('start', 0);
$submit = (isset($_POST['submit'])) ? true : false;

$default_key = 'c';
$sort_key = request_var('sk', $default_key);
$sort_dir = request_var('sd', 'a');


// Grab rank information for later
$ranks = phpbb::$cache->obtain_ranks();


// What do you want to do today? ... oops, I think that line is taken ...
switch ($mode)
{
	case 'leaders':
		// Display a listing of board admins, moderators
		include(phpbb::$phpbb_root_path . 'system/includes/functions_user.' . phpbb::$phpEx);

		$page_title = phpbb::$user->lang['THE_TEAM'];
		$template_html = 'memberlist_leaders.html';

		$user_ary = phpbb::$auth->acl_get_list(false, array('a_', 'm_'), false);

		$admin_id_ary = $global_mod_id_ary = $mod_id_ary = $forum_id_ary = array();
		foreach ($user_ary as $forum_id => $forum_ary)
		{
			foreach ($forum_ary as $auth_option => $id_ary)
			{
				if (!$forum_id)
				{
					if ($auth_option == 'a_')
					{
						$admin_id_ary = array_merge($admin_id_ary, $id_ary);
					}
					else
					{
						$global_mod_id_ary = array_merge($global_mod_id_ary, $id_ary);
					}
					continue;
				}
				else
				{
					$mod_id_ary = array_merge($mod_id_ary, $id_ary);
				}

				if ($forum_id)
				{
					foreach ($id_ary as $id)
					{
						$forum_id_ary[$id][] = $forum_id;
					}
				}
			}
		}

		$admin_id_ary = array_unique($admin_id_ary);
		$global_mod_id_ary = array_unique($global_mod_id_ary);

		$mod_id_ary = array_merge($mod_id_ary, $global_mod_id_ary);
		$mod_id_ary = array_unique($mod_id_ary);

		// Admin group id...
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = 'ADMINISTRATORS'";
		$result = phpbb::$db->sql_query($sql);
		$admin_group_id = (int) phpbb::$db->sql_fetchfield('group_id');
		phpbb::$db->sql_freeresult($result);

		// Get group memberships for the admin id ary...
		$admin_memberships = group_memberships($admin_group_id, $admin_id_ary);

		$admin_user_ids = array();

		if (!empty($admin_memberships))
		{
			// ok, we only need the user ids...
			foreach ($admin_memberships as $row)
			{
				$admin_user_ids[$row['user_id']] = true;
			}
		}
		unset($admin_memberships);

		$sql = 'SELECT forum_id, forum_name
			FROM ' . FORUMS_TABLE;
		$result = phpbb::$db->sql_query($sql);

		$forums = array();
		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$forums[$row['forum_id']] = $row['forum_name'];
		}
		phpbb::$db->sql_freeresult($result);

		$sql = phpbb::$db->sql_build_query('SELECT', array(
			'SELECT'	=> 'u.user_id, u.group_id as default_group, u.username, u.username_clean, u.user_colour, u.user_rank, u.user_posts, u.user_allow_pm, g.group_id, g.group_name, g.group_colour, g.group_type, ug.user_id as ug_user_id',

			'FROM'		=> array(
				USERS_TABLE		=> 'u',
				GROUPS_TABLE	=> 'g'
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USER_GROUP_TABLE => 'ug'),
					'ON'	=> 'ug.group_id = g.group_id AND ug.user_pending = 0 AND ug.user_id = ' . phpbb::$user->data['user_id']
				)
			),

			'WHERE'		=> phpbb::$db->sql_in_set('u.user_id', array_unique(array_merge($admin_id_ary, $mod_id_ary)), false, true) . '
				AND u.group_id = g.group_id',

			'ORDER_BY'	=> 'g.group_name ASC, u.username_clean ASC'
		));
		$result = phpbb::$db->sql_query($sql);

		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$which_row = (in_array($row['user_id'], $admin_id_ary)) ? 'admin' : 'mod';

			// We sort out admins not within the 'Administrators' group.
			// Else, we will list those as admin only having the permission to view logs for example.
			if ($which_row == 'admin' && empty($admin_user_ids[$row['user_id']]))
			{
				// Remove from admin_id_ary, because the user may be a mod instead
				unset($admin_id_ary[array_search($row['user_id'], $admin_id_ary)]);

				if (!in_array($row['user_id'], $mod_id_ary) && !in_array($row['user_id'], $global_mod_id_ary))
				{
					continue;
				}
				else
				{
					$which_row = 'mod';
				}
			}

			$s_forum_select = '';
			$undisclosed_forum = false;

			if (isset($forum_id_ary[$row['user_id']]) && !in_array($row['user_id'], $global_mod_id_ary))
			{
				if ($which_row == 'mod' && sizeof(array_diff(array_keys($forums), $forum_id_ary[$row['user_id']])))
				{
					foreach ($forum_id_ary[$row['user_id']] as $forum_id)
					{
						if (isset($forums[$forum_id]))
						{
							if (phpbb::$auth->acl_get('f_list', $forum_id))
							{
								$s_forum_select .= '<option value="">' . $forums[$forum_id] . '</option>';
							}
							else
							{
								$undisclosed_forum = true;
							}
						}
					}
				}
			}

			// If the mod is only moderating non-viewable forums we skip the user. There is no gain in displaying the person then...
			if (!$s_forum_select && $undisclosed_forum)
			{
//				$s_forum_select = '<option value="">' . phpbb::$user->lang['FORUM_UNDISCLOSED'] . '</option>';
				continue;
			}

			// The person is moderating several "public" forums, therefore the person should be listed, but not giving the real group name if hidden.
			if ($row['group_type'] == GROUP_HIDDEN && !phpbb::$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel') && $row['ug_user_id'] != phpbb::$user->data['user_id'])
			{
				$group_name = phpbb::$user->lang['GROUP_UNDISCLOSED'];
				$u_group = '';
			}
			else
			{
				$group_name = ($row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $row['group_name']] : $row['group_name'];
				$u_group = append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=group&amp;g=' . $row['group_id']);
			}

			$rank_title = $rank_img = '';
			get_user_rank($row['user_rank'], (($row['user_id'] == ANONYMOUS) ? false : $row['user_posts']), $rank_title, $rank_img, $rank_img_src);

			phpbb::$template->assign_block_vars($which_row, array(
				'USER_ID'		=> $row['user_id'],
				'FORUMS'		=> $s_forum_select,
				'RANK_TITLE'	=> $rank_title,
				'GROUP_NAME'	=> $group_name,
				'GROUP_COLOR'	=> $row['group_colour'],

				'RANK_IMG'		=> $rank_img,
				'RANK_IMG_SRC'	=> $rank_img_src,

				'U_GROUP'			=> $u_group,
				'U_PM'				=> (phpbb::$config['allow_privmsg'] && phpbb::$auth->acl_get('u_sendpm') && ($row['user_allow_pm'] || phpbb::$auth->acl_gets('a_', 'm_') || phpbb::$auth->acl_getf_global('m_'))) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=pm&amp;mode=compose&amp;u=' . $row['user_id']) : '',

				'USERNAME_FULL'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
				'USERNAME'			=> get_username_string('username', $row['user_id'], $row['username'], $row['user_colour']),
				'USER_COLOR'		=> get_username_string('colour', $row['user_id'], $row['username'], $row['user_colour']),
				'U_VIEW_PROFILE'	=> get_username_string('profile', $row['user_id'], $row['username'], $row['user_colour']),
			));
		}
		phpbb::$db->sql_freeresult($result);

		phpbb::$template->assign_vars(array(
			'PM_IMG'		=> phpbb::$user->img('icon_contact_pm', phpbb::$user->lang['SEND_PRIVATE_MESSAGE']))
		);
	break;

	case 'contact':

		$page_title = phpbb::$user->lang['IM_USER'];
		$template_html = 'memberlist_im.html';

		if (!phpbb::$auth->acl_get('u_sendim'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$presence_img = '';
		switch ($action)
		{
			case 'aim':
				$lang = 'AIM';
				$sql_field = 'user_aim';
				$s_select = 'S_SEND_AIM';
				$s_action = '';
			break;

			case 'msnm':
				$lang = 'MSNM';
				$sql_field = 'user_msnm';
				$s_select = 'S_SEND_MSNM';
				$s_action = '';
			break;

			case 'jabber':
				$lang = 'JABBER';
				$sql_field = 'user_jabber';
				$s_select = (@extension_loaded('xml') && phpbb::$config['jab_enable']) ? 'S_SEND_JABBER' : 'S_NO_SEND_JABBER';
				$s_action = append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, "mode=contact&amp;action=$action&amp;u=$user_id");
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}

		// Grab relevant data
		$sql = "SELECT user_id, username, user_email, user_lang, $sql_field
			FROM " . USERS_TABLE . "
			WHERE user_id = $user_id
				AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
		$result = phpbb::$db->sql_query($sql);
		$row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error('NO_USER');
		}
		else if (empty($row[$sql_field]))
		{
			trigger_error('IM_NO_DATA');
		}

		// Post data grab actions
		switch ($action)
		{
			case 'jabber':
				add_form_key('memberlist_messaging');

				if ($submit && @extension_loaded('xml') && phpbb::$config['jab_enable'])
				{
					if (check_form_key('memberlist_messaging'))
					{

						include_once(phpbb::$phpbb_root_path . 'system/core/messenger.' . phpbb::$phpEx);

						$subject = sprintf(phpbb::$user->lang['IM_JABBER_SUBJECT'], phpbb::$user->data['username'], phpbb::$config['server_name']);
						$message = utf8_normalize_nfc(request_var('message', '', true));

						if (empty($message))
						{
							trigger_error('EMPTY_MESSAGE_IM');
						}

						$messenger = new messenger(false);

						$messenger->template('profile_send_im', $row['user_lang']);
						$messenger->subject(htmlspecialchars_decode($subject));

						$messenger->replyto(phpbb::$user->data['user_email']);
						$messenger->im($row['user_jabber'], $row['username']);

						$messenger->assign_vars(array(
							'BOARD_CONTACT'	=> phpbb::$config['board_contact'],
							'FROM_USERNAME'	=> htmlspecialchars_decode(phpbb::$user->data['username']),
							'TO_USERNAME'	=> htmlspecialchars_decode($row['username']),
							'MESSAGE'		=> htmlspecialchars_decode($message))
						);

						$messenger->send(NOTIFY_IM);

						$s_select = 'S_SENT_JABBER';
					}
					else
					{
						trigger_error('FORM_INVALID');
					}
				}
			break;
		}

		// Send vars to the template
		phpbb::$template->assign_vars(array(
			'IM_CONTACT'	=> $row[$sql_field],
			'A_IM_CONTACT'	=> addslashes($row[$sql_field]),

			'U_AIM_CONTACT'	=> ($action == 'aim') ? 'aim:addbuddy?screenname=' . urlencode($row[$sql_field]) : '',
			'U_AIM_MESSAGE'	=> ($action == 'aim') ? 'aim:goim?screenname=' . urlencode($row[$sql_field]) . '&amp;message=' . urlencode(phpbb::$config['sitename']) : '',

			'USERNAME'		=> $row['username'],
			'CONTACT_NAME'	=> $row[$sql_field],
			'SITENAME'		=> phpbb::$config['sitename'],

			'PRESENCE_IMG'		=> $presence_img,

			'L_SEND_IM_EXPLAIN'	=> phpbb::$user->lang['IM_' . $lang],
			'L_IM_SENT_JABBER'	=> sprintf(phpbb::$user->lang['IM_SENT_JABBER'], $row['username']),

			$s_select			=> true,
			'S_IM_ACTION'		=> $s_action)
		);

	break;

	case 'viewprofile':
		// Display a profile
		if ($user_id == ANONYMOUS && !$username)
		{
			trigger_error('NO_USER');
		}

		// Get user...
		$sql = 'SELECT *
			FROM ' . USERS_TABLE . '
			WHERE ' . (($username) ? "username_clean = '" . phpbb::$db->sql_escape(utf8_clean_string($username)) . "'" : "user_id = $user_id");
		$result = phpbb::$db->sql_query($sql);
		$member = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		if (!$member)
		{
			trigger_error('NO_USER');
		}

		// a_user admins and founder are able to view inactive users and bots to be able to manage them more easily
		// Normal users are able to see at least users having only changed their profile settings but not yet reactivated.
		if (!phpbb::$auth->acl_get('a_user') && phpbb::$user->data['user_type'] != USER_FOUNDER)
		{
			if ($member['user_type'] == USER_IGNORE)
			{
				trigger_error('NO_USER');
			}
			else if ($member['user_type'] == USER_INACTIVE && $member['user_inactive_reason'] != INACTIVE_PROFILE)
			{
				trigger_error('NO_USER');
			}
		}

		$user_id = (int) $member['user_id'];

		// Get group memberships
		// Also get visiting user's groups to determine hidden group memberships if necessary.
		$auth_hidden_groups = ($user_id === (int) phpbb::$user->data['user_id'] || phpbb::$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel')) ? true : false;
		$sql_uid_ary = ($auth_hidden_groups) ? array($user_id) : array($user_id, (int) phpbb::$user->data['user_id']);

		// Do the SQL thang
		$sql = 'SELECT g.group_id, g.group_name, g.group_type, ug.user_id
			FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
			WHERE ' . phpbb::$db->sql_in_set('ug.user_id', $sql_uid_ary) . '
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0';
		$result = phpbb::$db->sql_query($sql);

		// Divide data into profile data and current user data
		$profile_groups = $user_groups = array();
		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$row['user_id'] = (int) $row['user_id'];
			$row['group_id'] = (int) $row['group_id'];

			if ($row['user_id'] == $user_id)
			{
				$profile_groups[] = $row;
			}
			else
			{
				$user_groups[$row['group_id']] = $row['group_id'];
			}
		}
		phpbb::$db->sql_freeresult($result);

		// Filter out hidden groups and sort groups by name
		$group_data = $group_sort = array();
		foreach ($profile_groups as $row)
		{
			if ($row['group_type'] == GROUP_SPECIAL)
			{
				// Lookup group name in language dictionary
				if (isset(phpbb::$user->lang['G_' . $row['group_name']]))
				{
					$row['group_name'] = phpbb::$user->lang['G_' . $row['group_name']];
				}
			}
			else if (!$auth_hidden_groups && $row['group_type'] == GROUP_HIDDEN && !isset($user_groups[$row['group_id']]))
			{
				// Skip over hidden groups the user cannot see
				continue;
			}

			$group_sort[$row['group_id']] = utf8_clean_string($row['group_name']);
			$group_data[$row['group_id']] = $row;
		}
		unset($profile_groups);
		unset($user_groups);
		asort($group_sort);

		$group_options = '';
		foreach ($group_sort as $group_id => $null)
		{
			$row = $group_data[$group_id];

			$group_options .= '<option value="' . $row['group_id'] . '"' . (($row['group_id'] == $member['group_id']) ? ' selected="selected"' : '') . '>' . $row['group_name'] . '</option>';
		}
		unset($group_data);
		unset($group_sort);

		// What colour is the zebra
		$sql = 'SELECT friend, foe
			FROM ' . ZEBRA_TABLE . "
			WHERE zebra_id = $user_id
				AND user_id = " . phpbb::$user->data['user_id'];

		$result = phpbb::$db->sql_query($sql);
		$row = phpbb::$db->sql_fetchrow($result);
		$foe = ($row['foe']) ? true : false;
		$friend = ($row['friend']) ? true : false;
		phpbb::$db->sql_freeresult($result);

		if (phpbb::$config['load_onlinetrack'])
		{
			$sql = 'SELECT MAX(session_time) AS session_time, MIN(session_viewonline) AS session_viewonline
				FROM ' . SESSIONS_TABLE . "
				WHERE session_user_id = $user_id";
			$result = phpbb::$db->sql_query($sql);
			$row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			$member['session_time'] = (isset($row['session_time'])) ? $row['session_time'] : 0;
			$member['session_viewonline'] = (isset($row['session_viewonline'])) ? $row['session_viewonline'] :	0;
			unset($row);
		}

		if (phpbb::$config['load_user_activity'])
		{
			display_user_activity($member);
		}

		// Do the relevant calculations
		$memberdays = max(1, round((time() - $member['user_regdate']) / 86400));
		$posts_per_day = $member['user_posts'] / $memberdays;
		$percentage = (phpbb::$config['num_posts']) ? min(100, ($member['user_posts'] / phpbb::$config['num_posts']) * 100) : 0;


		if ($member['user_sig'])
		{
			$member['user_sig'] = censor_text($member['user_sig']);

			if ($member['user_sig_bbcode_bitfield'])
			{
				include_once(phpbb::$phpbb_root_path . 'system/core/bbcode.' . phpbb::$phpEx);
				$bbcode = new bbcode();
				$bbcode->bbcode_second_pass($member['user_sig'], $member['user_sig_bbcode_uid'], $member['user_sig_bbcode_bitfield']);
			}

			$member['user_sig'] = bbcode_nl2br($member['user_sig']);
			$member['user_sig'] = smiley_text($member['user_sig']);
		}

		$poster_avatar = get_user_avatar($member['user_avatar'], $member['user_avatar_type'], $member['user_avatar_width'], $member['user_avatar_height']);

		// We need to check if the modules 'zebra' ('friends' & 'foes' mode),  'notes' ('user_notes' mode) and  'warn' ('warn_user' mode) are accessible to decide if we can display appropriate links
		$zebra_enabled = $friends_enabled = $foes_enabled = $user_notes_enabled = $warn_user_enabled = false;

		// Only check if the user is logged in
		if (phpbb::$user->data['is_registered'])
		{
			if (!class_exists('p_master'))
			{
				include(phpbb::$phpbb_root_path . 'system/core/module.' . phpbb::$phpEx);
			}
			$module = new p_master();

			$module->list_modules('ucp');
			$module->list_modules('mcp');

			$user_notes_enabled = ($module->loaded('notes', 'user_notes')) ? true : false;
			$warn_user_enabled = ($module->loaded('warn', 'warn_user')) ? true : false;
			$zebra_enabled = ($module->loaded('zebra')) ? true : false;
			$friends_enabled = ($module->loaded('zebra', 'friends')) ? true : false;
			$foes_enabled = ($module->loaded('zebra', 'foes')) ? true : false;

			unset($module);
		}

		phpbb::$template->assign_vars(show_profile($member, $user_notes_enabled, $warn_user_enabled));

		// Custom Profile Fields
		$profile_fields = array();
		if (phpbb::$config['load_cpf_viewprofile'])
		{
			include_once(phpbb::$phpbb_root_path . 'system/core/profile_fields.' . phpbb::$phpEx);
			$cp = new custom_profile();
			$profile_fields = $cp->generate_profile_fields_template('grab', $user_id);
			$profile_fields = (isset($profile_fields[$user_id])) ? $cp->generate_profile_fields_template('show', false, $profile_fields[$user_id]) : array();
		}

		// If the user has m_approve permission or a_user permission, then list then display unapproved posts
		if (phpbb::$auth->acl_getf_global('m_approve') || phpbb::$auth->acl_get('a_user'))
		{
			$sql = 'SELECT COUNT(post_id) as posts_in_queue
				FROM ' . POSTS_TABLE . '
				WHERE poster_id = ' . $user_id . '
					AND post_approved = 0';
			$result = phpbb::$db->sql_query($sql);
			$member['posts_in_queue'] = (int) phpbb::$db->sql_fetchfield('posts_in_queue');
			phpbb::$db->sql_freeresult($result);
		}
		else
		{
			$member['posts_in_queue'] = 0;
		}

		phpbb::$template->assign_vars(array(
			'L_POSTS_IN_QUEUE'	=> phpbb::$user->lang('NUM_POSTS_IN_QUEUE', $member['posts_in_queue']),

			'POSTS_DAY'			=> sprintf(phpbb::$user->lang['POST_DAY'], $posts_per_day),
			'POSTS_PCT'			=> sprintf(phpbb::$user->lang['POST_PCT'], $percentage),

			'OCCUPATION'	=> (!empty($member['user_occ'])) ? censor_text($member['user_occ']) : '',
			'INTERESTS'		=> (!empty($member['user_interests'])) ? censor_text($member['user_interests']) : '',
			'SIGNATURE'		=> $member['user_sig'],
			'POSTS_IN_QUEUE'=> $member['posts_in_queue'],

			'AVATAR_IMG'	=> $poster_avatar,
			'PM_IMG'		=> phpbb::$user->img('icon_contact_pm', phpbb::$user->lang['SEND_PRIVATE_MESSAGE']),
			'EMAIL_IMG'		=> phpbb::$user->img('icon_contact_email', phpbb::$user->lang['EMAIL']),
			'WWW_IMG'		=> phpbb::$user->img('icon_contact_www', phpbb::$user->lang['WWW']),
			'ICQ_IMG'		=> phpbb::$user->img('icon_contact_icq', phpbb::$user->lang['ICQ']),
			'AIM_IMG'		=> phpbb::$user->img('icon_contact_aim', phpbb::$user->lang['AIM']),
			'MSN_IMG'		=> phpbb::$user->img('icon_contact_msnm', phpbb::$user->lang['MSNM']),
			'YIM_IMG'		=> phpbb::$user->img('icon_contact_yahoo', phpbb::$user->lang['YIM']),
			'JABBER_IMG'	=> phpbb::$user->img('icon_contact_jabber', phpbb::$user->lang['JABBER']),
			'SEARCH_IMG'	=> phpbb::$user->img('icon_user_search', phpbb::$user->lang['SEARCH']),

			'S_PROFILE_ACTION'	=> append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=group'),
			'S_GROUP_OPTIONS'	=> $group_options,
			'S_CUSTOM_FIELDS'	=> (isset($profile_fields['row']) && sizeof($profile_fields['row'])) ? true : false,

			'U_USER_ADMIN'			=> (phpbb::$auth->acl_get('a_user')) ? append_sid(phpbb::$phpbb_root_path . "adm/index." . phpbb::$phpEx, 'i=users&amp;mode=overview&amp;u=' . $user_id, true, phpbb::$user->session_id) : '',
			'U_USER_BAN'			=> (phpbb::$auth->acl_get('m_ban') && $user_id != phpbb::$user->data['user_id']) ? append_sid(phpbb::$phpbb_root_path . "mcp." . phpbb::$phpEx, 'i=ban&amp;mode=user&amp;u=' . $user_id, true, phpbb::$user->session_id) : '',
			'U_MCP_QUEUE'			=> (phpbb::$auth->acl_getf_global('m_approve')) ? append_sid(phpbb::$phpbb_root_path . "mcp." . phpbb::$phpEx, 'i=queue', true, phpbb::$user->session_id) : '',

			'U_SWITCH_PERMISSIONS'	=> (phpbb::$auth->acl_get('a_switchperm') && phpbb::$user->data['user_id'] != $user_id) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, "mode=switch_perm&amp;u={$user_id}&amp;hash=" . generate_link_hash('switchperm')) : '',

			'S_USER_NOTES'		=> ($user_notes_enabled) ? true : false,
			'S_WARN_USER'		=> ($warn_user_enabled) ? true : false,
			'S_ZEBRA'			=> (phpbb::$user->data['user_id'] != $user_id && phpbb::$user->data['is_registered'] && $zebra_enabled) ? true : false,
			'U_ADD_FRIEND'		=> (!$friend && !$foe && $friends_enabled) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=zebra&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
			'U_ADD_FOE'			=> (!$friend && !$foe && $foes_enabled) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=zebra&amp;mode=foes&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
			'U_REMOVE_FRIEND'	=> ($friend && $friends_enabled) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=zebra&amp;remove=1&amp;usernames[]=' . $user_id) : '',
			'U_REMOVE_FOE'		=> ($foe && $foes_enabled) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=zebra&amp;remove=1&amp;mode=foes&amp;usernames[]=' . $user_id) : '',
		));

		if (!empty($profile_fields['row']))
		{
			phpbb::$template->assign_vars($profile_fields['row']);
		}

		if (!empty($profile_fields['blockrow']))
		{
			foreach ($profile_fields['blockrow'] as $field_data)
			{
				phpbb::$template->assign_block_vars('custom_fields', $field_data);
			}
		}

		// Inactive reason/account?
		if ($member['user_type'] == USER_INACTIVE)
		{
			phpbb::$user->add_lang('acp/common');

			$inactive_reason = phpbb::$user->lang['INACTIVE_REASON_UNKNOWN'];

			switch ($member['user_inactive_reason'])
			{
				case INACTIVE_REGISTER:
					$inactive_reason = phpbb::$user->lang['INACTIVE_REASON_REGISTER'];
				break;

				case INACTIVE_PROFILE:
					$inactive_reason = phpbb::$user->lang['INACTIVE_REASON_PROFILE'];
				break;

				case INACTIVE_MANUAL:
					$inactive_reason = phpbb::$user->lang['INACTIVE_REASON_MANUAL'];
				break;

				case INACTIVE_REMIND:
					$inactive_reason = phpbb::$user->lang['INACTIVE_REASON_REMIND'];
				break;
			}

			phpbb::$template->assign_vars(array(
				'S_USER_INACTIVE'		=> true,
				'USER_INACTIVE_REASON'	=> $inactive_reason)
			);
		}

		// Now generate page title
		$page_title = sprintf(phpbb::$user->lang['VIEWING_PROFILE'], $member['username']);
		$template_html = 'memberlist_view.html';

	break;

	case 'email':

		// Send an email
		$page_title = phpbb::$user->lang['SEND_EMAIL'];
		$template_html = 'memberlist_email.html';

		add_form_key('memberlist_email');

		if (!phpbb::$config['email_enable'])
		{
			trigger_error('EMAIL_DISABLED');
		}

		if (!phpbb::$auth->acl_get('u_sendemail'))
		{
			trigger_error('NO_EMAIL');
		}

		// Are we trying to abuse the facility?
		if (time() - phpbb::$user->data['user_emailtime'] < phpbb::$config['flood_interval'])
		{
			trigger_error('FLOOD_EMAIL_LIMIT');
		}

		// Determine action...
		$user_id = request_var('u', 0);
		$topic_id = request_var('t', 0);

		// Send email to user...
		if ($user_id)
		{
			if ($user_id == ANONYMOUS || !phpbb::$config['board_email_form'])
			{
				trigger_error('NO_EMAIL');
			}

			// Get the appropriate username, etc.
			$sql = 'SELECT username, user_email, user_allow_viewemail, user_lang, user_jabber, user_notify_type
				FROM ' . USERS_TABLE . "
				WHERE user_id = $user_id
					AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
			$result = phpbb::$db->sql_query($sql);
			$row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error('NO_USER');
			}

			// Can we send email to this user?
			if (!$row['user_allow_viewemail'] && !phpbb::$auth->acl_get('a_user'))
			{
				trigger_error('NO_EMAIL');
			}
		}
		else if ($topic_id)
		{
			// Send topic heads-up to email address
			$sql = 'SELECT forum_id, topic_title
				FROM ' . TOPICS_TABLE . "
				WHERE topic_id = $topic_id";
			$result = phpbb::$db->sql_query($sql);
			$row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error('NO_TOPIC');
			}

			if ($row['forum_id'])
			{
				if (!phpbb::$auth->acl_get('f_read', $row['forum_id']))
				{
					trigger_error('SORRY_AUTH_READ');
				}

				if (!phpbb::$auth->acl_get('f_email', $row['forum_id']))
				{
					trigger_error('NO_EMAIL');
				}
			}
			else
			{
				// If global announcement, we need to check if the user is able to at least read and email in one forum...
				if (!phpbb::$auth->acl_getf_global('f_read'))
				{
					trigger_error('SORRY_AUTH_READ');
				}

				if (!phpbb::$auth->acl_getf_global('f_email'))
				{
					trigger_error('NO_EMAIL');
				}
			}
		}
		else
		{
			trigger_error('NO_EMAIL');
		}

		$error = array();

		$name		= utf8_normalize_nfc(request_var('name', '', true));
		$email		= request_var('email', '');
		$email_lang = request_var('lang', phpbb::$config['default_lang']);
		$subject	= utf8_normalize_nfc(request_var('subject', '', true));
		$message	= utf8_normalize_nfc(request_var('message', '', true));
		$cc			= (isset($_POST['cc_email'])) ? true : false;
		$submit		= (isset($_POST['submit'])) ? true : false;

		if ($submit)
		{
			if (!check_form_key('memberlist_email'))
			{
				$error[] = 'FORM_INVALID';
			}
			if ($user_id)
			{
				if (!$subject)
				{
					$error[] = phpbb::$user->lang['EMPTY_SUBJECT_EMAIL'];
				}

				if (!$message)
				{
					$error[] = phpbb::$user->lang['EMPTY_MESSAGE_EMAIL'];
				}

				$name = $row['username'];
				$email_lang = $row['user_lang'];
				$email = $row['user_email'];
			}
			else
			{
				if (!$email || !preg_match('/^' . get_preg_expression('email') . '$/i', $email))
				{
					$error[] = phpbb::$user->lang['EMPTY_ADDRESS_EMAIL'];
				}

				if (!$name)
				{
					$error[] = phpbb::$user->lang['EMPTY_NAME_EMAIL'];
				}
			}

			if (!sizeof($error))
			{
				$sql = 'UPDATE ' . USERS_TABLE . '
					SET user_emailtime = ' . time() . '
					WHERE user_id = ' . phpbb::$user->data['user_id'];
				$result = phpbb::$db->sql_query($sql);

				include_once(phpbb::$phpbb_root_path . 'system/core/messenger.' . phpbb::$phpEx);
				$messenger = new messenger(false);
				$email_tpl = ($user_id) ? 'profile_send_email' : 'email_notify';

				$mail_to_users = array();

				$mail_to_users[] = array(
					'email_lang'		=> $email_lang,
					'email'				=> $email,
					'name'				=> $name,
					'username'			=> ($user_id) ? $row['username'] : '',
					'to_name'			=> $name,
					'user_jabber'		=> ($user_id) ? $row['user_jabber'] : '',
					'user_notify_type'	=> ($user_id) ? $row['user_notify_type'] : NOTIFY_EMAIL,
					'topic_title'		=> (!$user_id) ? $row['topic_title'] : '',
					'forum_id'			=> (!$user_id) ? $row['forum_id'] : 0,
				);

				// Ok, now the same email if CC specified, but without exposing the users email address
				if ($cc)
				{
					$mail_to_users[] = array(
						'email_lang'		=> phpbb::$user->data['user_lang'],
						'email'				=> phpbb::$user->data['user_email'],
						'name'				=> phpbb::$user->data['username'],
						'username'			=> phpbb::$user->data['username'],
						'to_name'			=> $name,
						'user_jabber'		=> phpbb::$user->data['user_jabber'],
						'user_notify_type'	=> ($user_id) ? phpbb::$user->data['user_notify_type'] : NOTIFY_EMAIL,
						'topic_title'		=> (!$user_id) ? $row['topic_title'] : '',
						'forum_id'			=> (!$user_id) ? $row['forum_id'] : 0,
					);
				}

				foreach ($mail_to_users as $row)
				{
					$messenger->template($email_tpl, $row['email_lang']);
					$messenger->replyto(phpbb::$user->data['user_email']);
					$messenger->to($row['email'], $row['name']);

					if ($user_id)
					{
						$messenger->subject(htmlspecialchars_decode($subject));
						$messenger->im($row['user_jabber'], $row['username']);
						$notify_type = $row['user_notify_type'];
					}
					else
					{
						$notify_type = NOTIFY_EMAIL;
					}

					$messenger->headers('X-AntiAbuse: Board servername - ' . phpbb::$config['server_name']);
					$messenger->headers('X-AntiAbuse: User_id - ' . phpbb::$user->data['user_id']);
					$messenger->headers('X-AntiAbuse: Username - ' . phpbb::$user->data['username']);
					$messenger->headers('X-AntiAbuse: User IP - ' . phpbb::$user->ip);

					$messenger->assign_vars(array(
						'BOARD_CONTACT'	=> phpbb::$config['board_contact'],
						'TO_USERNAME'	=> htmlspecialchars_decode($row['to_name']),
						'FROM_USERNAME'	=> htmlspecialchars_decode(phpbb::$user->data['username']),
						'MESSAGE'		=> htmlspecialchars_decode($message))
					);

					if ($topic_id)
					{
						$messenger->assign_vars(array(
							'TOPIC_NAME'	=> htmlspecialchars_decode($row['topic_title']),
							'U_TOPIC'		=> generate_board_url() . "/viewtopic.$phpEx?f=" . $row['forum_id'] . "&t=$topic_id")
						);
					}

					$messenger->send($notify_type);
				}

				meta_refresh(3, append_sid(phpbb::$phpbb_root_path . "index.$phpEx"));
				$message = ($user_id) ? sprintf(phpbb::$user->lang['RETURN_INDEX'], '<a href="' . append_sid(phpbb::$phpbb_root_path . "index.$phpEx") . '">', '</a>') : sprintf(phpbb::$user->lang['RETURN_TOPIC'],  '<a href="' . append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, "f={$row['forum_id']}&amp;t=$topic_id") . '">', '</a>');
				trigger_error(phpbb::$user->lang['EMAIL_SENT'] . '<br /><br />' . $message);
			}
		}

		if ($user_id)
		{
			phpbb::$template->assign_vars(array(
				'S_SEND_USER'	=> true,
				'USERNAME'		=> $row['username'],

				'L_EMAIL_BODY_EXPLAIN'	=> phpbb::$user->lang['EMAIL_BODY_EXPLAIN'],
				'S_POST_ACTION'			=> append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=email&amp;u=' . $user_id))
			);
		}
		else
		{
			phpbb::$template->assign_vars(array(
				'EMAIL'				=> $email,
				'NAME'				=> $name,
				'S_LANG_OPTIONS'	=> language_select($email_lang),

				'L_EMAIL_BODY_EXPLAIN'	=> phpbb::$user->lang['EMAIL_TOPIC_EXPLAIN'],
				'S_POST_ACTION'			=> append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=email&amp;t=' . $topic_id))
			);
		}

		phpbb::$template->assign_vars(array(
			'ERROR_MESSAGE'		=> (sizeof($error)) ? implode('<br />', $error) : '',
			'SUBJECT'			=> $subject,
			'MESSAGE'			=> $message,
			)
		);

	break;

	case 'group':
	default:
		// The basic memberlist
		$page_title = phpbb::$user->lang['MEMBERLIST'];
		$template_html = 'memberlist_body.html';

		// Sorting
		$sort_key_text = array('a' => phpbb::$user->lang['SORT_USERNAME'], 'b' => phpbb::$user->lang['SORT_LOCATION'], 'c' => phpbb::$user->lang['SORT_JOINED'], 'd' => phpbb::$user->lang['SORT_POST_COUNT'], 'f' => phpbb::$user->lang['WEBSITE'], 'g' => phpbb::$user->lang['ICQ'], 'h' => phpbb::$user->lang['AIM'], 'i' => phpbb::$user->lang['MSNM'], 'j' => phpbb::$user->lang['YIM'], 'k' => phpbb::$user->lang['JABBER']);
		$sort_key_sql = array('a' => 'u.username_clean', 'b' => 'u.user_from', 'c' => 'u.user_regdate', 'd' => 'u.user_posts', 'f' => 'u.user_website', 'g' => 'u.user_icq', 'h' => 'u.user_aim', 'i' => 'u.user_msnm', 'j' => 'u.user_yim', 'k' => 'u.user_jabber');

		if (phpbb::$auth->acl_get('a_user'))
		{
			$sort_key_text['e'] = phpbb::$user->lang['SORT_EMAIL'];
			$sort_key_sql['e'] = 'u.user_email';
		}

		if (phpbb::$auth->acl_get('u_viewonline'))
		{
			$sort_key_text['l'] = phpbb::$user->lang['SORT_LAST_ACTIVE'];
			$sort_key_sql['l'] = 'u.user_lastvisit';
		}

		$sort_key_text['m'] = phpbb::$user->lang['SORT_RANK'];
		$sort_key_sql['m'] = 'u.user_rank';

		$sort_dir_text = array('a' => phpbb::$user->lang['ASCENDING'], 'd' => phpbb::$user->lang['DESCENDING']);

		$s_sort_key = '';
		foreach ($sort_key_text as $key => $value)
		{
			$selected = ($sort_key == $key) ? ' selected="selected"' : '';
			$s_sort_key .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
		}

		$s_sort_dir = '';
		foreach ($sort_dir_text as $key => $value)
		{
			$selected = ($sort_dir == $key) ? ' selected="selected"' : '';
			$s_sort_dir .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
		}

		// Additional sorting options for user search ... if search is enabled, if not
		// then only admins can make use of this (for ACP functionality)
		$sql_select = $sql_where_data = $sql_from = $sql_where = $order_by = '';


		$form			= request_var('form', '');
		$field			= request_var('field', '');
		$select_single 	= request_var('select_single', false);

		// Search URL parameters, if any of these are in the URL we do a search
		$search_params = array('username', 'email', 'icq', 'aim', 'yahoo', 'msn', 'jabber', 'search_group_id', 'joined_select', 'active_select', 'count_select', 'joined', 'active', 'count', 'ip');

		// We validate form and field here, only id/class allowed
		$form = (!preg_match('/^[a-z0-9_-]+$/i', $form)) ? '' : $form;
		$field = (!preg_match('/^[a-z0-9_-]+$/i', $field)) ? '' : $field;
		if (($mode == 'searchuser' || sizeof(array_intersect(array_keys($_GET), $search_params)) > 0) && (phpbb::$config['load_search'] || phpbb::$auth->acl_get('a_')))
		{
			$username	= request_var('username', '', true);
			$email		= strtolower(request_var('email', ''));
			$icq		= request_var('icq', '');
			$aim		= request_var('aim', '');
			$yahoo		= request_var('yahoo', '');
			$msn		= request_var('msn', '');
			$jabber		= request_var('jabber', '');
			$search_group_id	= request_var('search_group_id', 0);

			// when using these, make sure that we actually have values defined in $find_key_match
			$joined_select	= request_var('joined_select', 'lt');
			$active_select	= request_var('active_select', 'lt');
			$count_select	= request_var('count_select', 'eq');

			$joined			= explode('-', request_var('joined', ''));
			$active			= explode('-', request_var('active', ''));
			$count			= (request_var('count', '') !== '') ? request_var('count', 0) : '';
			$ipdomain		= request_var('ip', '');

			$find_key_match = array('lt' => '<', 'gt' => '>', 'eq' => '=');

			$find_count = array('lt' => phpbb::$user->lang['LESS_THAN'], 'eq' => phpbb::$user->lang['EQUAL_TO'], 'gt' => phpbb::$user->lang['MORE_THAN']);
			$s_find_count = '';
			foreach ($find_count as $key => $value)
			{
				$selected = ($count_select == $key) ? ' selected="selected"' : '';
				$s_find_count .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}

			$find_time = array('lt' => phpbb::$user->lang['BEFORE'], 'gt' => phpbb::$user->lang['AFTER']);
			$s_find_join_time = '';
			foreach ($find_time as $key => $value)
			{
				$selected = ($joined_select == $key) ? ' selected="selected"' : '';
				$s_find_join_time .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}

			$s_find_active_time = '';
			foreach ($find_time as $key => $value)
			{
				$selected = ($active_select == $key) ? ' selected="selected"' : '';
				$s_find_active_time .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}

			$sql_where .= ($username) ? ' AND u.username_clean ' . phpbb::$db->sql_like_expression(str_replace('*', phpbb::$db->any_char, utf8_clean_string($username))) : '';
			$sql_where .= (phpbb::$auth->acl_get('a_user') && $email) ? ' AND u.user_email ' . phpbb::$db->sql_like_expression(str_replace('*', phpbb::$db->any_char, $email)) . ' ' : '';
			$sql_where .= ($icq) ? ' AND u.user_icq ' . phpbb::$db->sql_like_expression(str_replace('*', phpbb::$db->any_char, $icq)) . ' ' : '';
			$sql_where .= ($aim) ? ' AND u.user_aim ' . phpbb::$db->sql_like_expression(str_replace('*', phpbb::$db->any_char, $aim)) . ' ' : '';
			$sql_where .= ($yahoo) ? ' AND u.user_yim ' . phpbb::$db->sql_like_expression(str_replace('*', phpbb::$db->any_char, $yahoo)) . ' ' : '';
			$sql_where .= ($msn) ? ' AND u.user_msnm ' . phpbb::$db->sql_like_expression(str_replace('*', phpbb::$db->any_char, $msn)) . ' ' : '';
			$sql_where .= ($jabber) ? ' AND u.user_jabber ' . phpbb::$db->sql_like_expression(str_replace('*', phpbb::$db->any_char, $jabber)) . ' ' : '';
			$sql_where .= (is_numeric($count) && isset($find_key_match[$count_select])) ? ' AND u.user_posts ' . $find_key_match[$count_select] . ' ' . (int) $count . ' ' : '';

			if (isset($find_key_match[$joined_select]) && sizeof($joined) == 3)
			{
				// Before PHP 5.1 an error value -1 can be returned instead of false.
				// Theoretically gmmktime() can also legitimately return -1 as an actual timestamp.
				// But since we do not pass the $second parameter to gmmktime(),
				// an actual unix timestamp -1 cannot be returned in this case.
				// Thus we can check whether it is -1 and treat -1 as an error.
				$joined_time = gmmktime(0, 0, 0, (int) $joined[1], (int) $joined[2], (int) $joined[0]);

				if ($joined_time !== false && $joined_time !== -1)
				{
					$sql_where .= " AND u.user_regdate " . $find_key_match[$joined_select] . ' ' . $joined_time;
				}
			}

			if (isset($find_key_match[$active_select]) && sizeof($active) == 3 && phpbb::$auth->acl_get('u_viewonline'))
			{
				$active_time = gmmktime(0, 0, 0, (int) $active[1], (int) $active[2], (int) $active[0]);

				if ($active_time !== false && $active_time !== -1)
				{
					$sql_where .= " AND u.user_lastvisit " . $find_key_match[$active_select] . ' ' . $active_time;
				}
			}

			$sql_where .= ($search_group_id) ? " AND u.user_id = ug.user_id AND ug.group_id = $search_group_id AND ug.user_pending = 0 " : '';

			if ($search_group_id)
			{
				$sql_from = ', ' . USER_GROUP_TABLE . ' ug ';
			}

			if ($ipdomain && phpbb::$auth->acl_getf_global('m_info'))
			{
				if (strspn($ipdomain, 'abcdefghijklmnopqrstuvwxyz'))
				{
					$hostnames = gethostbynamel($ipdomain);

					if ($hostnames !== false)
					{
						$ips = "'" . implode('\', \'', array_map(array(phpbb::$db, 'sql_escape'), preg_replace('#([0-9]{1,3}\.[0-9]{1,3}[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#', "\\1", gethostbynamel($ipdomain)))) . "'";
					}
					else
					{
						$ips = false;
					}
				}
				else
				{
					$ips = "'" . str_replace('*', '%', phpbb::$db->sql_escape($ipdomain)) . "'";
				}

				if ($ips === false)
				{
					// A minor fudge but it does the job :D
					$sql_where .= " AND u.user_id = 0";
				}
				else
				{
					$ip_forums = array_keys(phpbb::$auth->acl_getf('m_info', true));

					$sql = 'SELECT DISTINCT poster_id
						FROM ' . POSTS_TABLE . '
						WHERE poster_ip ' . ((strpos($ips, '%') !== false) ? 'LIKE' : 'IN') . " ($ips)
							AND forum_id IN (0, " . implode(', ', $ip_forums) . ')';
					$result = phpbb::$db->sql_query($sql);

					if ($row = phpbb::$db->sql_fetchrow($result))
					{
						$ip_sql = array();
						do
						{
							$ip_sql[] = $row['poster_id'];
						}
						while ($row = phpbb::$db->sql_fetchrow($result));

						$sql_where .= ' AND ' . phpbb::$db->sql_in_set('u.user_id', $ip_sql);
					}
					else
					{
						// A minor fudge but it does the job :D
						$sql_where .= " AND u.user_id = 0";
					}
					unset($ip_forums);

					phpbb::$db->sql_freeresult($result);
				}
			}
		}

		$first_char = request_var('first_char', '');

		if ($first_char == 'other')
		{
			for ($i = 97; $i < 123; $i++)
			{
				$sql_where .= ' AND u.username_clean NOT ' . phpbb::$db->sql_like_expression(chr($i) . phpbb::$db->any_char);
			}
		}
		else if ($first_char)
		{
			$sql_where .= ' AND u.username_clean ' . phpbb::$db->sql_like_expression(substr($first_char, 0, 1) . phpbb::$db->any_char);
		}

		// Are we looking at a usergroup? If so, fetch additional info
		// and further restrict the user info query
		if ($mode == 'group')
		{
			// We JOIN here to save a query for determining membership for hidden groups. ;)
			$sql = 'SELECT g.*, ug.user_id
				FROM ' . GROUPS_TABLE . ' g
				LEFT JOIN ' . USER_GROUP_TABLE . ' ug ON (ug.user_pending = 0 AND ug.user_id = ' . phpbb::$user->data['user_id'] . " AND ug.group_id = $group_id)
				WHERE g.group_id = $group_id";
			$result = phpbb::$db->sql_query($sql);
			$group_row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			if (!$group_row)
			{
				trigger_error('NO_GROUP');
			}

			switch ($group_row['group_type'])
			{
				case GROUP_OPEN:
					$group_row['l_group_type'] = 'OPEN';
				break;

				case GROUP_CLOSED:
					$group_row['l_group_type'] = 'CLOSED';
				break;

				case GROUP_HIDDEN:
					$group_row['l_group_type'] = 'HIDDEN';

					// Check for membership or special permissions
					if (!phpbb::$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel') && $group_row['user_id'] != phpbb::$user->data['user_id'])
					{
						trigger_error('NO_GROUP');
					}
				break;

				case GROUP_SPECIAL:
					$group_row['l_group_type'] = 'SPECIAL';
				break;

				case GROUP_FREE:
					$group_row['l_group_type'] = 'FREE';
				break;
			}

			// Misusing the avatar function for displaying group avatars...
			$avatar_img = get_user_avatar($group_row['group_avatar'], $group_row['group_avatar_type'], $group_row['group_avatar_width'], $group_row['group_avatar_height'], 'GROUP_AVATAR');

			$rank_title = $rank_img = $rank_img_src = '';
			if ($group_row['group_rank'])
			{
				if (isset($ranks['special'][$group_row['group_rank']]))
				{
					$rank_title = $ranks['special'][$group_row['group_rank']]['rank_title'];
				}
				$rank_img = (!empty($ranks['special'][$group_row['group_rank']]['rank_image'])) ? '<img src="' . phpbb::$config['ranks_path'] . '/' . $ranks['special'][$group_row['group_rank']]['rank_image'] . '" alt="' . $ranks['special'][$group_row['group_rank']]['rank_title'] . '" title="' . $ranks['special'][$group_row['group_rank']]['rank_title'] . '" /><br />' : '';
				$rank_img_src = (!empty($ranks['special'][$group_row['group_rank']]['rank_image'])) ? phpbb::$config['ranks_path'] . '/' . $ranks['special'][$group_row['group_rank']]['rank_image'] : '';
			}
			else
			{
				$rank_title = '';
				$rank_img = '';
				$rank_img_src = '';
			}

			phpbb::$template->assign_vars(array(
				'GROUP_DESC'	=> generate_text_for_display($group_row['group_desc'], $group_row['group_desc_uid'], $group_row['group_desc_bitfield'], $group_row['group_desc_options']),
				'GROUP_NAME'	=> ($group_row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $group_row['group_name']] : $group_row['group_name'],
				'GROUP_COLOR'	=> $group_row['group_colour'],
				'GROUP_TYPE'	=> phpbb::$user->lang['GROUP_IS_' . $group_row['l_group_type']],
				'GROUP_RANK'	=> $rank_title,

				'AVATAR_IMG'	=> $avatar_img,
				'RANK_IMG'		=> $rank_img,
				'RANK_IMG_SRC'	=> $rank_img_src,

				'U_PM'			=> (phpbb::$auth->acl_get('u_sendpm') && phpbb::$auth->acl_get('u_masspm_group') && $group_row['group_receive_pm'] && phpbb::$config['allow_privmsg'] && phpbb::$config['allow_mass_pm']) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=pm&amp;mode=compose&amp;g=' . $group_id) : '',)
			);

			$sql_select = ', ug.group_leader';
			$sql_from = ', ' . USER_GROUP_TABLE . ' ug ';
			$order_by = 'ug.group_leader DESC, ';

			$sql_where .= " AND ug.user_pending = 0 AND u.user_id = ug.user_id AND ug.group_id = $group_id";
			$sql_where_data = " AND u.user_id = ug.user_id AND ug.group_id = $group_id";
		}

		// Sorting and order
		if (!isset($sort_key_sql[$sort_key]))
		{
			$sort_key = $default_key;
		}

		$order_by .= $sort_key_sql[$sort_key] . ' ' . (($sort_dir == 'a') ? 'ASC' : 'DESC');

		// Unfortunately we must do this here for sorting by rank, else the sort order is applied wrongly
		if ($sort_key == 'm')
		{
			$order_by .= ', u.user_posts DESC';
		}

		// Count the users ...
		if ($sql_where)
		{
			$sql = 'SELECT COUNT(u.user_id) AS total_users
				FROM ' . USERS_TABLE . " u$sql_from
				WHERE u.user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ")
				$sql_where";
			$result = phpbb::$db->sql_query($sql);
			$total_users = (int) phpbb::$db->sql_fetchfield('total_users');
			phpbb::$db->sql_freeresult($result);
		}
		else
		{
			$total_users = phpbb::$config['num_users'];
		}

		$s_char_options = '<option value=""' . ((!$first_char) ? ' selected="selected"' : '') . '>&nbsp; &nbsp;</option>';
		for ($i = 97; $i < 123; $i++)
		{
			$s_char_options .= '<option value="' . chr($i) . '"' . (($first_char == chr($i)) ? ' selected="selected"' : '') . '>' . chr($i-32) . '</option>';
		}
		$s_char_options .= '<option value="other"' . (($first_char == 'other') ? ' selected="selected"' : '') . '>' . phpbb::$user->lang['OTHER'] . '</option>';

		// Build a relevant pagination_url
		$params = $sort_params = array();

		// We do not use request_var() here directly to save some calls (not all variables are set)
		$check_params = array(
			'g'				=> array('g', 0),
			'sk'			=> array('sk', $default_key),
			'sd'			=> array('sd', 'a'),
			'form'			=> array('form', ''),
			'field'			=> array('field', ''),
			'select_single'	=> array('select_single', $select_single),
			'username'		=> array('username', '', true),
			'email'			=> array('email', ''),
			'icq'			=> array('icq', ''),
			'aim'			=> array('aim', ''),
			'yahoo'			=> array('yahoo', ''),
			'msn'			=> array('msn', ''),
			'jabber'		=> array('jabber', ''),
			'search_group_id'	=> array('search_group_id', 0),
			'joined_select'	=> array('joined_select', 'lt'),
			'active_select'	=> array('active_select', 'lt'),
			'count_select'	=> array('count_select', 'eq'),
			'joined'		=> array('joined', ''),
			'active'		=> array('active', ''),
			'count'			=> (request_var('count', '') !== '') ? array('count', 0) : array('count', ''),
			'ip'			=> array('ip', ''),
			'first_char'	=> array('first_char', ''),
		);

		foreach ($check_params as $key => $call)
		{
			if (!isset($_REQUEST[$key]))
			{
				continue;
			}

			$param = call_user_func_array('request_var', $call);
			$param = urlencode($key) . '=' . ((is_string($param)) ? urlencode($param) : $param);
			$params[] = $param;

			if ($key != 'sk' && $key != 'sd')
			{
				$sort_params[] = $param;
			}
		}

		$u_hide_find_member = append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, "start=$start" . (!empty($params) ? '&amp;' . implode('&amp;', $params) : ''));

		if ($mode)
		{
			$params[] = "mode=$mode";
		}
		$sort_params[] = "mode=$mode";

		$pagination_url = append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, implode('&amp;', $params));
		$sort_url = append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, implode('&amp;', $sort_params));

		unset($search_params, $sort_params);

		// Some search user specific data
		if ($mode == 'searchuser' && (phpbb::$config['load_search'] || phpbb::$auth->acl_get('a_')))
		{
			$group_selected = request_var('search_group_id', 0);
			$s_group_select = '<option value="0"' . ((!$group_selected) ? ' selected="selected"' : '') . '>&nbsp;</option>';
			$group_ids = array();

			/**
			* @todo add this to a separate function (function is responsible for returning the groups the user is able to see based on the users group membership)
			*/

			if (phpbb::$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel'))
			{
				$sql = 'SELECT group_id, group_name, group_type
					FROM ' . GROUPS_TABLE;

				if (!phpbb::$config['coppa_enable'])
				{
					$sql .= " WHERE group_name <> 'REGISTERED_COPPA'";
				}

				$sql .= ' ORDER BY group_name ASC';
			}
			else
			{
				$sql = 'SELECT g.group_id, g.group_name, g.group_type
					FROM ' . GROUPS_TABLE . ' g
					LEFT JOIN ' . USER_GROUP_TABLE . ' ug
						ON (
							g.group_id = ug.group_id
							AND ug.user_id = ' . phpbb::$user->data['user_id'] . '
							AND ug.user_pending = 0
						)
					WHERE (g.group_type <> ' . GROUP_HIDDEN . ' OR ug.user_id = ' . phpbb::$user->data['user_id'] . ')';

				if (!phpbb::$config['coppa_enable'])
				{
					$sql .= " AND g.group_name <> 'REGISTERED_COPPA'";
				}

				$sql .= ' ORDER BY g.group_name ASC';
			}
			$result = phpbb::$db->sql_query($sql);

			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$group_ids[] = $row['group_id'];
				$s_group_select .= '<option value="' . $row['group_id'] . '"' . (($group_selected == $row['group_id']) ? ' selected="selected"' : '') . '>' . (($row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $row['group_name']] : $row['group_name']) . '</option>';
			}
			phpbb::$db->sql_freeresult($result);

			if ($group_selected !== 0 && !in_array($group_selected, $group_ids))
			{
				trigger_error('NO_GROUP');
			}

			phpbb::$template->assign_vars(array(
				'USERNAME'	=> $username,
				'EMAIL'		=> $email,
				'ICQ'		=> $icq,
				'AIM'		=> $aim,
				'YAHOO'		=> $yahoo,
				'MSNM'		=> $msn,
				'JABBER'	=> $jabber,
				'JOINED'	=> implode('-', $joined),
				'ACTIVE'	=> implode('-', $active),
				'COUNT'		=> $count,
				'IP'		=> $ipdomain,

				'S_IP_SEARCH_ALLOWED'	=> (phpbb::$auth->acl_getf_global('m_info')) ? true : false,
				'S_EMAIL_SEARCH_ALLOWED'=> (phpbb::$auth->acl_get('a_user')) ? true : false,
				'S_IN_SEARCH_POPUP'		=> ($form && $field) ? true : false,
				'S_SEARCH_USER'			=> true,
				'S_FORM_NAME'			=> $form,
				'S_FIELD_NAME'			=> $field,
				'S_SELECT_SINGLE'		=> $select_single,
				'S_COUNT_OPTIONS'		=> $s_find_count,
				'S_SORT_OPTIONS'		=> $s_sort_key,
				'S_JOINED_TIME_OPTIONS'	=> $s_find_join_time,
				'S_ACTIVE_TIME_OPTIONS'	=> $s_find_active_time,
				'S_GROUP_SELECT'		=> $s_group_select,
				'S_USER_SEARCH_ACTION'	=> append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, "mode=searchuser&amp;form=$form&amp;field=$field"))
			);
		}

		// Get us some users :D
		$sql = "SELECT u.user_id
			FROM " . USERS_TABLE . " u
				$sql_from
			WHERE u.user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ")
				$sql_where
			ORDER BY $order_by";
		$result = phpbb::$db->sql_query_limit($sql, phpbb::$config['topics_per_page'], $start);

		$user_list = array();
		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$user_list[] = (int) $row['user_id'];
		}
		phpbb::$db->sql_freeresult($result);
		$leaders_set = false;
		// So, did we get any users?
		if (sizeof($user_list))
		{
			// Session time?! Session time...
			$sql = 'SELECT session_user_id, MAX(session_time) AS session_time
				FROM ' . SESSIONS_TABLE . '
				WHERE session_time >= ' . (time() - phpbb::$config['session_length']) . '
					AND ' . phpbb::$db->sql_in_set('session_user_id', $user_list) . '
				GROUP BY session_user_id';
			$result = phpbb::$db->sql_query($sql);

			$session_times = array();
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$session_times[$row['session_user_id']] = $row['session_time'];
			}
			phpbb::$db->sql_freeresult($result);

			// Do the SQL thang
			if ($mode == 'group')
			{
				$sql = "SELECT u.*
						$sql_select
					FROM " . USERS_TABLE . " u
						$sql_from
					WHERE " . phpbb::$db->sql_in_set('u.user_id', $user_list) . "
						$sql_where_data";
			}
			else
			{
				$sql = 'SELECT *
					FROM ' . USERS_TABLE . '
					WHERE ' . phpbb::$db->sql_in_set('user_id', $user_list);
			}
			$result = phpbb::$db->sql_query($sql);

			$id_cache = array();
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$row['session_time'] = (!empty($session_times[$row['user_id']])) ? $session_times[$row['user_id']] : 0;
				$row['last_visit'] = (!empty($row['session_time'])) ? $row['session_time'] : $row['user_lastvisit'];

				$id_cache[$row['user_id']] = $row;
			}
			phpbb::$db->sql_freeresult($result);

			// Load custom profile fields
			if (phpbb::$config['load_cpf_memberlist'])
			{
				include_once(phpbb::$phpbb_root_path . 'system/core/profile_fields.' . phpbb::$phpEx);
				$cp = new custom_profile();

				// Grab all profile fields from users in id cache for later use - similar to the poster cache
				$profile_fields_cache = $cp->generate_profile_fields_template('grab', $user_list);
			}

			// If we sort by last active date we need to adjust the id cache due to user_lastvisit not being the last active date...
			if ($sort_key == 'l')
			{
//				uasort($id_cache, create_function('$first, $second', "return (\$first['last_visit'] == \$second['last_visit']) ? 0 : ((\$first['last_visit'] < \$second['last_visit']) ? $lesser_than : ($lesser_than * -1));"));
				usort($user_list,  '_sort_last_active');
			}

			for ($i = 0, $end = sizeof($user_list); $i < $end; ++$i)
			{
				$user_id = $user_list[$i];
				$row =& $id_cache[$user_id];
				$is_leader = (isset($row['group_leader']) && $row['group_leader']) ? true : false;
				$leaders_set = ($leaders_set || $is_leader);

				$cp_row = array();
				if (phpbb::$config['load_cpf_memberlist'])
				{
					$cp_row = (isset($profile_fields_cache[$user_id])) ? $cp->generate_profile_fields_template('show', false, $profile_fields_cache[$user_id]) : array();
				}

				$memberrow = array_merge(show_profile($row), array(
					'ROW_NUMBER'		=> $i + ($start + 1),

					'S_CUSTOM_PROFILE'	=> (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
					'S_GROUP_LEADER'	=> $is_leader,

					'U_VIEW_PROFILE'	=> append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=viewprofile&amp;u=' . $user_id))
				);

				if (isset($cp_row['row']) && sizeof($cp_row['row']))
				{
					$memberrow = array_merge($memberrow, $cp_row['row']);
				}

				phpbb::$template->assign_block_vars('memberrow', $memberrow);

				if (isset($cp_row['blockrow']) && sizeof($cp_row['blockrow']))
				{
					foreach ($cp_row['blockrow'] as $field_data)
					{
						phpbb::$template->assign_block_vars('memberrow.custom_fields', $field_data);
					}
				}

				unset($id_cache[$user_id]);
			}
		}

		// Generate page
		phpbb::$template->assign_vars(array(
			'PAGINATION'	=> generate_pagination($pagination_url, $total_users, phpbb::$config['topics_per_page'], $start),
			'PAGE_NUMBER'	=> on_page($total_users, phpbb::$config['topics_per_page'], $start),
			'TOTAL_USERS'	=> ($total_users == 1) ? phpbb::$user->lang['LIST_USER'] : sprintf(phpbb::$user->lang['LIST_USERS'], $total_users),

			'PROFILE_IMG'	=> phpbb::$user->img('icon_user_profile', phpbb::$user->lang['PROFILE']),
			'PM_IMG'		=> phpbb::$user->img('icon_contact_pm', phpbb::$user->lang['SEND_PRIVATE_MESSAGE']),
			'EMAIL_IMG'		=> phpbb::$user->img('icon_contact_email', phpbb::$user->lang['EMAIL']),
			'WWW_IMG'		=> phpbb::$user->img('icon_contact_www', phpbb::$user->lang['WWW']),
			'ICQ_IMG'		=> phpbb::$user->img('icon_contact_icq', phpbb::$user->lang['ICQ']),
			'AIM_IMG'		=> phpbb::$user->img('icon_contact_aim', phpbb::$user->lang['AIM']),
			'MSN_IMG'		=> phpbb::$user->img('icon_contact_msnm', phpbb::$user->lang['MSNM']),
			'YIM_IMG'		=> phpbb::$user->img('icon_contact_yahoo', phpbb::$user->lang['YIM']),
			'JABBER_IMG'	=> phpbb::$user->img('icon_contact_jabber', phpbb::$user->lang['JABBER']),
			'SEARCH_IMG'	=> phpbb::$user->img('icon_user_search', phpbb::$user->lang['SEARCH']),

			'U_FIND_MEMBER'			=> (phpbb::$config['load_search'] || phpbb::$auth->acl_get('a_')) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=searchuser' . (($start) ? "&amp;start=$start" : '') . (!empty($params) ? '&amp;' . implode('&amp;', $params) : '')) : '',
			'U_HIDE_FIND_MEMBER'	=> ($mode == 'searchuser') ? $u_hide_find_member : '',
			'U_SORT_USERNAME'		=> $sort_url . '&amp;sk=a&amp;sd=' . (($sort_key == 'a' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_FROM'			=> $sort_url . '&amp;sk=b&amp;sd=' . (($sort_key == 'b' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_JOINED'			=> $sort_url . '&amp;sk=c&amp;sd=' . (($sort_key == 'c' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_POSTS'			=> $sort_url . '&amp;sk=d&amp;sd=' . (($sort_key == 'd' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_EMAIL'			=> $sort_url . '&amp;sk=e&amp;sd=' . (($sort_key == 'e' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_WEBSITE'		=> $sort_url . '&amp;sk=f&amp;sd=' . (($sort_key == 'f' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_LOCATION'		=> $sort_url . '&amp;sk=b&amp;sd=' . (($sort_key == 'b' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_ICQ'			=> $sort_url . '&amp;sk=g&amp;sd=' . (($sort_key == 'g' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_AIM'			=> $sort_url . '&amp;sk=h&amp;sd=' . (($sort_key == 'h' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_MSN'			=> $sort_url . '&amp;sk=i&amp;sd=' . (($sort_key == 'i' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_YIM'			=> $sort_url . '&amp;sk=j&amp;sd=' . (($sort_key == 'j' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_ACTIVE'			=> (phpbb::$auth->acl_get('u_viewonline')) ? $sort_url . '&amp;sk=l&amp;sd=' . (($sort_key == 'l' && $sort_dir == 'a') ? 'd' : 'a') : '',
			'U_SORT_RANK'			=> $sort_url . '&amp;sk=m&amp;sd=' . (($sort_key == 'm' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_LIST_CHAR'			=> $sort_url . '&amp;sk=a&amp;sd=' . (($sort_key == 'l' && $sort_dir == 'a') ? 'd' : 'a'),

			'S_SHOW_GROUP'		=> ($mode == 'group') ? true : false,
			'S_VIEWONLINE'		=> phpbb::$auth->acl_get('u_viewonline'),
			'S_LEADERS_SET'		=> $leaders_set,
			'S_MODE_SELECT'		=> $s_sort_key,
			'S_ORDER_SELECT'	=> $s_sort_dir,
			'S_CHAR_OPTIONS'	=> $s_char_options,
			'S_MODE_ACTION'		=> $pagination_url)
		);
}

// Output the page
page_header($page_title, false);

phpbb::$template->set_filenames(array(
	'body' => $template_html)
);
make_jumpbox(append_sid(phpbb::$phpbb_root_path . "viewforum.$phpEx"));

page_footer();

/**
* Prepare profile data
*/
function show_profile($data, $user_notes_enabled = false, $warn_user_enabled = false)
{

	$username = $data['username'];
	$user_id = $data['user_id'];

	$rank_title = $rank_img = $rank_img_src = '';
	get_user_rank($data['user_rank'], (($user_id == ANONYMOUS) ? false : $data['user_posts']), $rank_title, $rank_img, $rank_img_src);

	if ((!empty($data['user_allow_viewemail']) && phpbb::$auth->acl_get('u_sendemail')) || phpbb::$auth->acl_get('a_user'))
	{
		$email = (phpbb::$config['board_email_form'] && phpbb::$config['email_enable']) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=email&amp;u=' . $user_id) : ((phpbb::$config['board_hide_emails'] && !phpbb::$auth->acl_get('a_user')) ? '' : 'mailto:' . $data['user_email']);
	}
	else
	{
		$email = '';
	}

	if (phpbb::$config['load_onlinetrack'])
	{
		$update_time = phpbb::$config['load_online_time'] * 60;
		$online = (time() - $update_time < $data['session_time'] && ((isset($data['session_viewonline']) && $data['session_viewonline']) || phpbb::$auth->acl_get('u_viewonline'))) ? true : false;
	}
	else
	{
		$online = false;
	}

	if ($data['user_allow_viewonline'] || phpbb::$auth->acl_get('u_viewonline'))
	{
		$last_visit = (!empty($data['session_time'])) ? $data['session_time'] : $data['user_lastvisit'];
	}
	else
	{
		$last_visit = '';
	}

	$age = '';

	if (phpbb::$config['allow_birthdays'] && $data['user_birthday'])
	{
		list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $data['user_birthday']));

		if ($bday_year)
		{
			$now = getdate(time() + phpbb::$user->timezone + phpbb::$user->dst - date('Z'));

			$diff = $now['mon'] - $bday_month;
			if ($diff == 0)
			{
				$diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
			}
			else
			{
				$diff = ($diff < 0) ? 1 : 0;
			}

			$age = (int) ($now['year'] - $bday_year - $diff);
		}
	}

	// Dump it out to the template
	return array(
		'AGE'			=> $age,
		'RANK_TITLE'	=> $rank_title,
		'JOINED'		=> phpbb::$user->format_date($data['user_regdate']),
		'VISITED'		=> (empty($last_visit)) ? ' - ' : phpbb::$user->format_date($last_visit),
		'POSTS'			=> ($data['user_posts']) ? $data['user_posts'] : 0,
		'WARNINGS'		=> isset($data['user_warnings']) ? $data['user_warnings'] : 0,

		'USERNAME_FULL'		=> get_username_string('full', $user_id, $username, $data['user_colour']),
		'USERNAME'			=> get_username_string('username', $user_id, $username, $data['user_colour']),
		'USER_COLOR'		=> get_username_string('colour', $user_id, $username, $data['user_colour']),
		'U_VIEW_PROFILE'	=> get_username_string('profile', $user_id, $username, $data['user_colour']),

		'A_USERNAME'		=> addslashes(get_username_string('username', $user_id, $username, $data['user_colour'])),

		'AVATAR_IMG'		=> get_user_avatar($data['user_avatar'], $data['user_avatar_type'], $data['user_avatar_width'], $data['user_avatar_height']),
		'ONLINE_IMG'		=> (!phpbb::$config['load_onlinetrack']) ? '' : (($online) ? phpbb::$user->img('icon_user_online', 'ONLINE') : phpbb::$user->img('icon_user_offline', 'OFFLINE')),
		'S_ONLINE'			=> (phpbb::$config['load_onlinetrack'] && $online) ? true : false,
		'RANK_IMG'			=> $rank_img,
		'RANK_IMG_SRC'		=> $rank_img_src,
		'ICQ_STATUS_IMG'	=> (!empty($data['user_icq'])) ? '<img src="http://web.icq.com/whitepages/online?icq=' . $data['user_icq'] . '&amp;img=5" width="18" height="18" />' : '',
		'S_JABBER_ENABLED'	=> (phpbb::$config['jab_enable']) ? true : false,

		'S_WARNINGS'	=> (phpbb::$auth->acl_getf_global('m_') || phpbb::$auth->acl_get('m_warn')) ? true : false,

		'U_SEARCH_USER'	=> (phpbb::$auth->acl_get('u_search')) ? append_sid(phpbb::$phpbb_root_path . "search." . phpbb::$phpEx, "author_id=$user_id&amp;sr=posts") : '',
		'U_NOTES'		=> ($user_notes_enabled && phpbb::$auth->acl_getf_global('m_')) ? append_sid(phpbb::$phpbb_root_path . "mcp." . phpbb::$phpEx, 'i=notes&amp;mode=user_notes&amp;u=' . $user_id, true, phpbb::$user->session_id) : '',
		'U_WARN'		=> ($warn_user_enabled && phpbb::$auth->acl_get('m_warn')) ? append_sid(phpbb::$phpbb_root_path . "mcp." . phpbb::$phpEx, 'i=warn&amp;mode=warn_user&amp;u=' . $user_id, true, phpbb::$user->session_id) : '',
		'U_PM'			=> (phpbb::$config['allow_privmsg'] && phpbb::$auth->acl_get('u_sendpm') && ($data['user_allow_pm'] || phpbb::$auth->acl_gets('a_', 'm_') || phpbb::$auth->acl_getf_global('m_'))) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=pm&amp;mode=compose&amp;u=' . $user_id) : '',
		'U_EMAIL'		=> $email,
		'U_WWW'			=> (!empty($data['user_website'])) ? $data['user_website'] : '',
		'U_SHORT_WWW'			=> (!empty($data['user_website'])) ? ((strlen($data['user_website']) > 55) ? substr($data['user_website'], 0, 39) . ' ... ' . substr($data['user_website'], -10) : $data['user_website']) : '',
		'U_ICQ'			=> ($data['user_icq']) ? 'http://www.icq.com/people/' . urlencode($data['user_icq']) . '/' : '',
		'U_AIM'			=> ($data['user_aim'] && phpbb::$auth->acl_get('u_sendim')) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=contact&amp;action=aim&amp;u=' . $user_id) : '',
		'U_YIM'			=> ($data['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($data['user_yim']) . '&amp;.src=pg' : '',
		'U_MSN'			=> ($data['user_msnm'] && phpbb::$auth->acl_get('u_sendim')) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=contact&amp;action=msnm&amp;u=' . $user_id) : '',
		'U_JABBER'		=> ($data['user_jabber'] && phpbb::$auth->acl_get('u_sendim')) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=contact&amp;action=jabber&amp;u=' . $user_id) : '',
		'LOCATION'		=> ($data['user_from']) ? $data['user_from'] : '',

		'USER_ICQ'			=> $data['user_icq'],
		'USER_AIM'			=> $data['user_aim'],
		'USER_YIM'			=> $data['user_yim'],
		'USER_MSN'			=> $data['user_msnm'],
		'USER_JABBER'		=> $data['user_jabber'],
		'USER_JABBER_IMG'	=> ($data['user_jabber']) ? phpbb::$user->img('icon_contact_jabber', $data['user_jabber']) : '',

		'L_VIEWING_PROFILE'	=> sprintf(phpbb::$user->lang['VIEWING_PROFILE'], $username),
	);
}

function _sort_last_active($first, $second)
{
	global $id_cache, $sort_dir;

	$lesser_than = ($sort_dir === 'd') ? -1 : 1;

	if (isset($id_cache[$first]['group_leader']) && $id_cache[$first]['group_leader'] && (!isset($id_cache[$second]['group_leader']) || !$id_cache[$second]['group_leader']))
	{
		return -1;
	}
	else if (isset($id_cache[$second]['group_leader']) && (!isset($id_cache[$first]['group_leader']) || !$id_cache[$first]['group_leader']) && $id_cache[$second]['group_leader'])
	{
		return 1;
	}
	else
	{
		return $lesser_than * (int) ($id_cache[$first]['last_visit'] - $id_cache[$second]['last_visit']);
	}
}

?>