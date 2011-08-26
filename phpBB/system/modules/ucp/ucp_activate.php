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
* ucp_activate
* User activation
* @package ucp
*/
class ucp_activate
{
	var $u_action;

	function main($id, $mode)
	{



		$user_id = request_var('u', 0);
		$key = request_var('k', '');

		$sql = 'SELECT user_id, username, user_type, user_email, user_newpasswd, user_lang, user_notify_type, user_actkey, user_inactive_reason
			FROM ' . USERS_TABLE . "
			WHERE user_id = $user_id";
		$result = phpbb::$db->sql_query($sql);
		$user_row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		if (!$user_row)
		{
			trigger_error('NO_USER');
		}

		if ($user_row['user_type'] <> USER_INACTIVE && !$user_row['user_newpasswd'])
		{
			meta_refresh(3, append_sid(phpbb::$phpbb_root_path . "index.$phpEx"));
			trigger_error('ALREADY_ACTIVATED');
		}

		if (($user_row['user_inactive_reason'] ==  INACTIVE_MANUAL) || $user_row['user_actkey'] != $key)
		{
			trigger_error('WRONG_ACTIVATION');
		}

		// Do not allow activating by non administrators when admin activation is on
		// Only activation type the user should be able to do is INACTIVE_REMIND
		// or activate a new password which is not an activation state :@
		if (!$user_row['user_newpasswd'] && $user_row['user_inactive_reason'] != INACTIVE_REMIND && phpbb::$config['require_activation'] == USER_ACTIVATION_ADMIN && !phpbb::$auth->acl_get('a_user'))
		{
			if (!phpbb::$user->data['is_registered'])
			{
				login_box('', phpbb::$user->lang['NO_AUTH_OPERATION']);
			}
			trigger_error('NO_AUTH_OPERATION');
		}

		$update_password = ($user_row['user_newpasswd']) ? true : false;

		if ($update_password)
		{
			$sql_ary = array(
				'user_actkey'		=> '',
				'user_password'		=> $user_row['user_newpasswd'],
				'user_newpasswd'	=> '',
				'user_pass_convert'	=> 0,
				'user_login_attempts'	=> 0,
			);

			$sql = 'UPDATE ' . USERS_TABLE . '
				SET ' . phpbb::$db->sql_build_array('UPDATE', $sql_ary) . '
				WHERE user_id = ' . $user_row['user_id'];
			phpbb::$db->sql_query($sql);

			add_log('user', $user_row['user_id'], 'LOG_USER_NEW_PASSWORD', $user_row['username']);
		}

		if (!$update_password)
		{
			include_once(phpbb::$phpbb_root_path . 'system/includes/functions_user.' . phpbb::$phpEx);

			user_active_flip('activate', $user_row['user_id']);

			$sql = 'UPDATE ' . USERS_TABLE . "
				SET user_actkey = ''
				WHERE user_id = {$user_row['user_id']}";
			phpbb::$db->sql_query($sql);

			// Create the correct logs
			add_log('user', $user_row['user_id'], 'LOG_USER_ACTIVE_USER');
			if (phpbb::$auth->acl_get('a_user'))
			{
				add_log('admin', 'LOG_USER_ACTIVE', $user_row['username']);
			}
		}

		if (phpbb::$config['require_activation'] == USER_ACTIVATION_ADMIN && !$update_password)
		{
			include_once(phpbb::$phpbb_root_path . 'system/includes/functions_messenger.' . phpbb::$phpEx);

			$messenger = new messenger(false);

			$messenger->template('admin_welcome_activated', $user_row['user_lang']);

			$messenger->to($user_row['user_email'], $user_row['username']);

			$messenger->headers('X-AntiAbuse: Board servername - ' . phpbb::$config['server_name']);
			$messenger->headers('X-AntiAbuse: User_id - ' . phpbb::$user->data['user_id']);
			$messenger->headers('X-AntiAbuse: Username - ' . phpbb::$user->data['username']);
			$messenger->headers('X-AntiAbuse: User IP - ' . phpbb::$user->ip);

			$messenger->assign_vars(array(
				'USERNAME'	=> htmlspecialchars_decode($user_row['username']))
			);

			$messenger->send($user_row['user_notify_type']);

			$message = 'ACCOUNT_ACTIVE_ADMIN';
		}
		else
		{
			if (!$update_password)
			{
				$message = ($user_row['user_inactive_reason'] == INACTIVE_PROFILE) ? 'ACCOUNT_ACTIVE_PROFILE' : 'ACCOUNT_ACTIVE';
			}
			else
			{
				$message = 'PASSWORD_ACTIVATED';
			}
		}

		meta_refresh(3, append_sid(phpbb::$phpbb_root_path . "index.$phpEx"));
		trigger_error(phpbb::$user->lang[$message]);
	}
}

?>