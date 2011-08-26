<?php
/**
*
* @package ucp
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
* View private message
*/
function view_message($id, $mode, $folder_id, $msg_id, $folder, $message_row)
{

	phpbb::$user->add_lang(array('viewtopic', 'memberlist'));

	$msg_id		= (int) $msg_id;
	$folder_id	= (int) $folder_id;
	$author_id	= (int) $message_row['author_id'];
	$view		= request_var('view', '');

	// Not able to view message, it was deleted by the sender
	if ($message_row['pm_deleted'])
	{
		$meta_info = append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, "i=pm&amp;folder=$folder_id");
		$message = phpbb::$user->lang['NO_AUTH_READ_REMOVED_MESSAGE'];

		$message .= '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_FOLDER'], '<a href="' . $meta_info . '">', '</a>');
		trigger_error($message);
	}

	// Do not allow hold messages to be seen
	if ($folder_id == PRIVMSGS_HOLD_BOX)
	{
		trigger_error('NO_AUTH_READ_HOLD_MESSAGE');
	}

	// Grab icons
	$icons = phpbb::$cache->obtain_icons();

	$bbcode = false;

	// Instantiate BBCode if need be
	if ($message_row['bbcode_bitfield'])
	{
		include(phpbb::$phpbb_root_path . 'system/includes/bbcode.' . phpbb::$phpEx);
		$bbcode = new bbcode($message_row['bbcode_bitfield']);
	}

	// Assign TO/BCC Addresses to template
	write_pm_addresses(array('to' => $message_row['to_address'], 'bcc' => $message_row['bcc_address']), $author_id);

	$user_info = get_user_information($author_id, $message_row);

	// Parse the message and subject
	$message = censor_text($message_row['message_text']);

	// Second parse bbcode here
	if ($message_row['bbcode_bitfield'])
	{
		$bbcode->bbcode_second_pass($message, $message_row['bbcode_uid'], $message_row['bbcode_bitfield']);
	}

	// Always process smilies after parsing bbcodes
	$message = bbcode_nl2br($message);
	$message = smiley_text($message);

	// Replace naughty words such as farty pants
	$message_row['message_subject'] = censor_text($message_row['message_subject']);

	// Editing information
	if ($message_row['message_edit_count'] && phpbb::$config['display_last_edited'])
	{
		$l_edit_time_total = ($message_row['message_edit_count'] == 1) ? phpbb::$user->lang['EDITED_TIME_TOTAL'] : phpbb::$user->lang['EDITED_TIMES_TOTAL'];
		$l_edited_by = '<br /><br />' . sprintf($l_edit_time_total, (!$message_row['message_edit_user']) ? $message_row['username'] : $message_row['message_edit_user'], phpbb::$user->format_date($message_row['message_edit_time'], false, true), $message_row['message_edit_count']);
	}
	else
	{
		$l_edited_by = '';
	}

	// Pull attachment data
	$display_notice = false;
	$attachments = array();

	if ($message_row['message_attachment'] && phpbb::$config['allow_pm_attach'])
	{
		if (phpbb::$auth->acl_get('u_pm_download'))
		{
			$sql = 'SELECT *
				FROM ' . ATTACHMENTS_TABLE . "
				WHERE post_msg_id = $msg_id
					AND in_message = 1
				ORDER BY filetime DESC, post_msg_id ASC";
			$result = phpbb::$db->sql_query($sql);

			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$attachments[] = $row;
			}
			phpbb::$db->sql_freeresult($result);

			// No attachments exist, but message table thinks they do so go ahead and reset attach flags
			if (!sizeof($attachments))
			{
				$sql = 'UPDATE ' . PRIVMSGS_TABLE . "
					SET message_attachment = 0
					WHERE msg_id = $msg_id";
				phpbb::$db->sql_query($sql);
			}
		}
		else
		{
			$display_notice = true;
		}
	}

	// Assign inline attachments
	if (!empty($attachments))
	{
		$update_count = array();
		parse_attachments(false, $message, $attachments, $update_count);

		// Update the attachment download counts
		if (sizeof($update_count))
		{
			$sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
				SET download_count = download_count + 1
				WHERE ' . phpbb::$db->sql_in_set('attach_id', array_unique($update_count));
			phpbb::$db->sql_query($sql);
		}
	}

	$user_info['sig'] = '';

	$signature = ($message_row['enable_sig'] && phpbb::$config['allow_sig'] && phpbb::$auth->acl_get('u_sig') && phpbb::$user->optionget('viewsigs')) ? $user_info['user_sig'] : '';

	// End signature parsing, only if needed
	if ($signature)
	{
		$signature = censor_text($signature);

		if ($user_info['user_sig_bbcode_bitfield'])
		{
			if ($bbcode === false)
			{
				include(phpbb::$phpbb_root_path . 'system/includes/bbcode.' . phpbb::$phpEx);
				$bbcode = new bbcode($user_info['user_sig_bbcode_bitfield']);
			}

			$bbcode->bbcode_second_pass($signature, $user_info['user_sig_bbcode_uid'], $user_info['user_sig_bbcode_bitfield']);
		}

		$signature = bbcode_nl2br($signature);
		$signature = smiley_text($signature);
	}

	$url = append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=pm');

	// Number of "to" recipients
	$num_recipients = (int) preg_match_all('/:?(u|g)_([0-9]+):?/', $message_row['to_address'], $match);

	$bbcode_status	= (phpbb::$config['allow_bbcode'] && phpbb::$config['auth_bbcode_pm'] && phpbb::$auth->acl_get('u_pm_bbcode')) ? true : false;

	phpbb::$template->assign_vars(array(
		'MESSAGE_AUTHOR_FULL'		=> get_username_string('full', $author_id, $user_info['username'], $user_info['user_colour'], $user_info['username']),
		'MESSAGE_AUTHOR_COLOUR'		=> get_username_string('colour', $author_id, $user_info['username'], $user_info['user_colour'], $user_info['username']),
		'MESSAGE_AUTHOR'			=> get_username_string('username', $author_id, $user_info['username'], $user_info['user_colour'], $user_info['username']),
		'U_MESSAGE_AUTHOR'			=> get_username_string('profile', $author_id, $user_info['username'], $user_info['user_colour'], $user_info['username']),

		'RANK_TITLE'		=> $user_info['rank_title'],
		'RANK_IMG'			=> $user_info['rank_image'],
		'AUTHOR_AVATAR'		=> (isset($user_info['avatar'])) ? $user_info['avatar'] : '',
		'AUTHOR_JOINED'		=> phpbb::$user->format_date($user_info['user_regdate']),
		'AUTHOR_POSTS'		=> (int) $user_info['user_posts'],
		'AUTHOR_FROM'		=> (!empty($user_info['user_from'])) ? $user_info['user_from'] : '',

		'ONLINE_IMG'		=> (!phpbb::$config['load_onlinetrack']) ? '' : ((isset($user_info['online']) && $user_info['online']) ? phpbb::$user->img('icon_user_online', phpbb::$user->lang['ONLINE']) : phpbb::$user->img('icon_user_offline', phpbb::$user->lang['OFFLINE'])),
		'S_ONLINE'			=> (!phpbb::$config['load_onlinetrack']) ? false : ((isset($user_info['online']) && $user_info['online']) ? true : false),
		'DELETE_IMG'		=> phpbb::$user->img('icon_post_delete', phpbb::$user->lang['DELETE_MESSAGE']),
		'INFO_IMG'			=> phpbb::$user->img('icon_post_info', phpbb::$user->lang['VIEW_PM_INFO']),
		'PROFILE_IMG'		=> phpbb::$user->img('icon_user_profile', phpbb::$user->lang['READ_PROFILE']),
		'EMAIL_IMG'			=> phpbb::$user->img('icon_contact_email', phpbb::$user->lang['SEND_EMAIL']),
		'QUOTE_IMG'			=> phpbb::$user->img('icon_post_quote', phpbb::$user->lang['POST_QUOTE_PM']),
		'REPLY_IMG'			=> phpbb::$user->img('button_pm_reply', phpbb::$user->lang['POST_REPLY_PM']),
		'REPORT_IMG'		=> phpbb::$user->img('icon_post_report', 'REPORT_PM'),
		'EDIT_IMG'			=> phpbb::$user->img('icon_post_edit', phpbb::$user->lang['POST_EDIT_PM']),
		'MINI_POST_IMG'		=> phpbb::$user->img('icon_post_target', phpbb::$user->lang['PM']),

		'SENT_DATE'			=> ($view == 'print') ? phpbb::$user->format_date($message_row['message_time'], false, true) : phpbb::$user->format_date($message_row['message_time']),
		'SUBJECT'			=> $message_row['message_subject'],
		'MESSAGE'			=> $message,
		'SIGNATURE'			=> ($message_row['enable_sig']) ? $signature : '',
		'EDITED_MESSAGE'	=> $l_edited_by,
		'MESSAGE_ID'		=> $message_row['msg_id'],

		'U_PM'			=> (phpbb::$config['allow_privmsg'] && phpbb::$auth->acl_get('u_sendpm') && ($user_info['user_allow_pm'] || phpbb::$auth->acl_gets('a_', 'm_') || phpbb::$auth->acl_getf_global('m_'))) ? append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=pm&amp;mode=compose&amp;u=' . $author_id) : '',
		'U_WWW'			=> (!empty($user_info['user_website'])) ? $user_info['user_website'] : '',
		'U_ICQ'			=> ($user_info['user_icq']) ? 'http://www.icq.com/people' . urlencode($user_info['user_icq']) . '/' : '',
		'U_AIM'			=> ($user_info['user_aim'] && phpbb::$auth->acl_get('u_sendim')) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=contact&amp;action=aim&amp;u=' . $author_id) : '',
		'U_YIM'			=> ($user_info['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($user_info['user_yim']) . '&amp;.src=pg' : '',
		'U_MSN'			=> ($user_info['user_msnm'] && phpbb::$auth->acl_get('u_sendim')) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=contact&amp;action=msnm&amp;u=' . $author_id) : '',
		'U_JABBER'		=> ($user_info['user_jabber'] && phpbb::$auth->acl_get('u_sendim')) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=contact&amp;action=jabber&amp;u=' . $author_id) : '',

		'U_DELETE'			=> (phpbb::$auth->acl_get('u_pm_delete')) ? "$url&amp;mode=compose&amp;action=delete&amp;f=$folder_id&amp;p=" . $message_row['msg_id'] : '',
		'U_EMAIL'			=> $user_info['email'],
		'U_REPORT'			=> (phpbb::$config['allow_pm_report']) ? append_sid(phpbb::$phpbb_root_path . "report." . phpbb::$phpEx, "pm=" . $message_row['msg_id']) : '',
		'U_QUOTE'			=> (phpbb::$auth->acl_get('u_sendpm') && $author_id != ANONYMOUS) ? "$url&amp;mode=compose&amp;action=quote&amp;f=$folder_id&amp;p=" . $message_row['msg_id'] : '',
		'U_EDIT'			=> (($message_row['message_time'] > time() - (phpbb::$config['pm_edit_time'] * 60) || !phpbb::$config['pm_edit_time']) && $folder_id == PRIVMSGS_OUTBOX && phpbb::$auth->acl_get('u_pm_edit')) ? "$url&amp;mode=compose&amp;action=edit&amp;f=$folder_id&amp;p=" . $message_row['msg_id'] : '',
		'U_POST_REPLY_PM'	=> (phpbb::$auth->acl_get('u_sendpm') && $author_id != ANONYMOUS) ? "$url&amp;mode=compose&amp;action=reply&amp;f=$folder_id&amp;p=" . $message_row['msg_id'] : '',
		'U_POST_REPLY_ALL'	=> (phpbb::$auth->acl_get('u_sendpm') && $author_id != ANONYMOUS) ? "$url&amp;mode=compose&amp;action=reply&amp;f=$folder_id&amp;reply_to_all=1&amp;p=" . $message_row['msg_id'] : '',
		'U_PREVIOUS_PM'		=> "$url&amp;f=$folder_id&amp;p=" . $message_row['msg_id'] . "&amp;view=previous",
		'U_NEXT_PM'			=> "$url&amp;f=$folder_id&amp;p=" . $message_row['msg_id'] . "&amp;view=next",

		'U_PM_ACTION'		=> $url . '&amp;mode=compose&amp;f=' . $folder_id . '&amp;p=' . $message_row['msg_id'],

		'S_HAS_ATTACHMENTS'	=> (sizeof($attachments)) ? true : false,
		'S_DISPLAY_NOTICE'	=> $display_notice && $message_row['message_attachment'],
		'S_AUTHOR_DELETED'	=> ($author_id == ANONYMOUS) ? true : false,
		'S_SPECIAL_FOLDER'	=> in_array($folder_id, array(PRIVMSGS_NO_BOX, PRIVMSGS_OUTBOX)),
		'S_PM_RECIPIENTS'	=> $num_recipients,
		'S_BBCODE_ALLOWED'	=> ($bbcode_status) ? 1 : 0,

		'U_PRINT_PM'		=> (phpbb::$config['print_pm'] && phpbb::$auth->acl_get('u_pm_printpm')) ? "$url&amp;f=$folder_id&amp;p=" . $message_row['msg_id'] . "&amp;view=print" : '',
		'U_FORWARD_PM'		=> (phpbb::$config['forward_pm'] && phpbb::$auth->acl_get('u_sendpm') && phpbb::$auth->acl_get('u_pm_forward')) ? "$url&amp;mode=compose&amp;action=forward&amp;f=$folder_id&amp;p=" . $message_row['msg_id'] : '')
	);

	// Display not already displayed Attachments for this post, we already parsed them. ;)
	if (isset($attachments) && sizeof($attachments))
	{
		foreach ($attachments as $attachment)
		{
			phpbb::$template->assign_block_vars('attachment', array(
				'DISPLAY_ATTACHMENT'	=> $attachment)
			);
		}
	}

	if (!isset($_REQUEST['view']) || $_REQUEST['view'] != 'print')
	{
		// Message History
		if (message_history($msg_id, phpbb::$user->data['user_id'], $message_row, $folder))
		{
			phpbb::$template->assign_var('S_DISPLAY_HISTORY', true);
		}
	}
}

/**
* Get user information (only for message display)
*/
function get_user_information($user_id, $user_row)
{

	if (!$user_id)
	{
		return array();
	}

	if (empty($user_row))
	{
		$sql = 'SELECT *
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $user_id;
		$result = phpbb::$db->sql_query($sql);
		$user_row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);
	}

	// Some standard values
	$user_row['online'] = false;
	$user_row['rank_title'] = $user_row['rank_image'] = $user_row['rank_image_src'] = $user_row['email'] = '';

	// Generate online information for user
	if (phpbb::$config['load_onlinetrack'])
	{
		$sql = 'SELECT session_user_id, MAX(session_time) as online_time, MIN(session_viewonline) AS viewonline
			FROM ' . SESSIONS_TABLE . "
			WHERE session_user_id = $user_id
			GROUP BY session_user_id";
		$result = phpbb::$db->sql_query_limit($sql, 1);
		$row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		$update_time = phpbb::$config['load_online_time'] * 60;
		if ($row)
		{
			$user_row['online'] = (time() - $update_time < $row['online_time'] && ($row['viewonline'] || phpbb::$auth->acl_get('u_viewonline'))) ? true : false;
		}
	}

	if (!function_exists('get_user_avatar'))
	{
		include(phpbb::$phpbb_root_path . 'system/includes/functions_display.' . phpbb::$phpEx);
	}

	$user_row['avatar'] = (phpbb::$user->optionget('viewavatars')) ? get_user_avatar($user_row['user_avatar'], $user_row['user_avatar_type'], $user_row['user_avatar_width'], $user_row['user_avatar_height']) : '';

	get_user_rank($user_row['user_rank'], $user_row['user_posts'], $user_row['rank_title'], $user_row['rank_image'], $user_row['rank_image_src']);

	if ((!empty($user_row['user_allow_viewemail']) && phpbb::$auth->acl_get('u_sendemail')) || phpbb::$auth->acl_get('a_email'))
	{
		$user_row['email'] = (phpbb::$config['board_email_form'] && phpbb::$config['email_enable']) ? append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, "mode=email&amp;u=$user_id") : (((phpbb::$config['board_hide_emails'] && !phpbb::$auth->acl_get('a_email')) || empty($user_row['user_email'])) ? '' : 'mailto:' . $user_row['user_email']);
	}

	return $user_row;
}

?>