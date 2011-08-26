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
* ucp_remind
* Sending password reminders
* @package ucp
*/
class ucp_remind
{
	var $u_action;

	function main($id, $mode)
	{



		$username	= request_var('username', '', true);
		$email		= strtolower(request_var('email', ''));
		$submit		= (isset($_POST['submit'])) ? true : false;

		if ($submit)
		{
			$sql = 'SELECT user_id, username, user_permissions, user_email, user_jabber, user_notify_type, user_type, user_lang, user_inactive_reason
				FROM ' . USERS_TABLE . "
				WHERE user_email_hash = '" . phpbb::$db->sql_escape(phpbb_email_hash($email)) . "'
					AND username_clean = '" . phpbb::$db->sql_escape(utf8_clean_string($username)) . "'";
			$result = phpbb::$db->sql_query($sql);
			$user_row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			if (!$user_row)
			{
				trigger_error('NO_EMAIL_USER');
			}

			if ($user_row['user_type'] == USER_IGNORE)
			{
				trigger_error('NO_USER');
			}

			if ($user_row['user_type'] == USER_INACTIVE)
			{
				if ($user_row['user_inactive_reason'] == INACTIVE_MANUAL)
				{
					trigger_error('ACCOUNT_DEACTIVATED');
				}
				else
				{
					trigger_error('ACCOUNT_NOT_ACTIVATED');
				}
			}

			// Check users permissions
			$auth2 = new auth();
			$auth2->acl($user_row);

			if (!$auth2->acl_get('u_chgpasswd'))
			{
				trigger_error('NO_AUTH_PASSWORD_REMINDER');
			}

			$server_url = generate_board_url();

			// Make password at least 8 characters long, make it longer if admin wants to.
			// gen_rand_string() however has a limit of 12 or 13.
			$user_password = gen_rand_string_friendly(max(8, mt_rand((int) phpbb::$config['min_pass_chars'], (int) phpbb::$config['max_pass_chars'])));

			// For the activation key a random length between 6 and 10 will do.
			$user_actkey = gen_rand_string(mt_rand(6, 10));

			$sql = 'UPDATE ' . USERS_TABLE . "
				SET user_newpasswd = '" . phpbb::$db->sql_escape(phpbb_hash($user_password)) . "', user_actkey = '" . phpbb::$db->sql_escape($user_actkey) . "'
				WHERE user_id = " . $user_row['user_id'];
			phpbb::$db->sql_query($sql);

			include_once(phpbb::$phpbb_root_path . 'system/includes/functions_messenger.' . phpbb::$phpEx);

			$messenger = new messenger(false);

			$messenger->template('user_activate_passwd', $user_row['user_lang']);

			$messenger->to($user_row['user_email'], $user_row['username']);
			$messenger->im($user_row['user_jabber'], $user_row['username']);

			$messenger->assign_vars(array(
				'USERNAME'		=> htmlspecialchars_decode($user_row['username']),
				'PASSWORD'		=> htmlspecialchars_decode($user_password),
				'U_ACTIVATE'	=> "$server_url/ucp.$phpEx?mode=activate&u={$user_row['user_id']}&k=$user_actkey")
			);

			$messenger->send($user_row['user_notify_type']);

			meta_refresh(3, append_sid(phpbb::$phpbb_root_path . "index.$phpEx"));

			$message = phpbb::$user->lang['PASSWORD_UPDATED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_INDEX'], '<a href="' . append_sid(phpbb::$phpbb_root_path . "index.$phpEx") . '">', '</a>');
			trigger_error($message);
		}

		phpbb::$template->assign_vars(array(
			'USERNAME'			=> $username,
			'EMAIL'				=> $email,
			'S_PROFILE_ACTION'	=> append_sid(phpbb::$phpbb_root_path . 'ucp.' . $phpEx, 'mode=sendpassword'))
		);

		$this->tpl_name = 'ucp_remind';
		$this->page_title = 'UCP_REMIND';
	}
}

?>