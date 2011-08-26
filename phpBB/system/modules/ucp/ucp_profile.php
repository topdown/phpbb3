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
* ucp_profile
* Changing profile settings
*
* @todo what about pertaining user_sig_options?
* @package ucp
*/
class ucp_profile
{
	var $u_action;

	function main($id, $mode)
	{


		phpbb::$user->add_lang('posting');

		$preview	= (!empty($_POST['preview'])) ? true : false;
		$submit		= (!empty($_POST['submit'])) ? true : false;
		$delete		= (!empty($_POST['delete'])) ? true : false;
		$error = $data = array();
		$s_hidden_fields = '';

		switch ($mode)
		{
			case 'reg_details':

				$data = array(
					'username'			=> utf8_normalize_nfc(request_var('username', phpbb::$user->data['username'], true)),
					'email'				=> strtolower(request_var('email', phpbb::$user->data['user_email'])),
					'email_confirm'		=> strtolower(request_var('email_confirm', '')),
					'new_password'		=> request_var('new_password', '', true),
					'cur_password'		=> request_var('cur_password', '', true),
					'password_confirm'	=> request_var('password_confirm', '', true),
				);

				add_form_key('ucp_reg_details');

				if ($submit)
				{
					// Do not check cur_password, it is the old one.
					$check_ary = array(
						'new_password'		=> array(
							array('string', true, phpbb::$config['min_pass_chars'], phpbb::$config['max_pass_chars']),
							array('password')),
						'password_confirm'	=> array('string', true, phpbb::$config['min_pass_chars'], phpbb::$config['max_pass_chars']),
						'email'				=> array(
							array('string', false, 6, 60),
							array('email')),
						'email_confirm'		=> array('string', true, 6, 60),
					);

					if (phpbb::$auth->acl_get('u_chgname') && phpbb::$config['allow_namechange'])
					{
						$check_ary['username'] = array(
							array('string', false, phpbb::$config['min_name_chars'], phpbb::$config['max_name_chars']),
							array('username'),
						);
					}

					$error = validate_data($data, $check_ary);

					if (phpbb::$auth->acl_get('u_chgpasswd') && $data['new_password'] && $data['password_confirm'] != $data['new_password'])
					{
						$error[] = 'NEW_PASSWORD_ERROR';
					}

					if (($data['new_password'] || (phpbb::$auth->acl_get('u_chgemail') && $data['email'] != phpbb::$user->data['user_email']) || ($data['username'] != phpbb::$user->data['username'] && phpbb::$auth->acl_get('u_chgname') && phpbb::$config['allow_namechange'])) && !phpbb_check_hash($data['cur_password'], phpbb::$user->data['user_password']))
					{
						$error[] = 'CUR_PASSWORD_ERROR';
					}

					// Only check the new password against the previous password if there have been no errors
					if (!sizeof($error) && phpbb::$auth->acl_get('u_chgpasswd') && $data['new_password'] && phpbb_check_hash($data['new_password'], phpbb::$user->data['user_password']))
					{
						$error[] = 'SAME_PASSWORD_ERROR';
					}

					if (phpbb::$auth->acl_get('u_chgemail') && $data['email'] != phpbb::$user->data['user_email'] && $data['email_confirm'] != $data['email'])
					{
						$error[] = 'NEW_EMAIL_ERROR';
					}

					if (!check_form_key('ucp_reg_details'))
					{
						$error[] = 'FORM_INVALID';
					}

					if (!sizeof($error))
					{
						$sql_ary = array(
							'username'			=> (phpbb::$auth->acl_get('u_chgname') && phpbb::$config['allow_namechange']) ? $data['username'] : phpbb::$user->data['username'],
							'username_clean'	=> (phpbb::$auth->acl_get('u_chgname') && phpbb::$config['allow_namechange']) ? utf8_clean_string($data['username']) : phpbb::$user->data['username_clean'],
							'user_email'		=> (phpbb::$auth->acl_get('u_chgemail')) ? $data['email'] : phpbb::$user->data['user_email'],
							'user_email_hash'	=> (phpbb::$auth->acl_get('u_chgemail')) ? phpbb_email_hash($data['email']) : phpbb::$user->data['user_email_hash'],
							'user_password'		=> (phpbb::$auth->acl_get('u_chgpasswd') && $data['new_password']) ? phpbb_hash($data['new_password']) : phpbb::$user->data['user_password'],
							'user_passchg'		=> (phpbb::$auth->acl_get('u_chgpasswd') && $data['new_password']) ? time() : 0,
						);

						if (phpbb::$auth->acl_get('u_chgname') && phpbb::$config['allow_namechange'] && $data['username'] != phpbb::$user->data['username'])
						{
							add_log('user', phpbb::$user->data['user_id'], 'LOG_USER_UPDATE_NAME', phpbb::$user->data['username'], $data['username']);
						}

						if (phpbb::$auth->acl_get('u_chgpasswd') && $data['new_password'] && !phpbb_check_hash($data['new_password'], phpbb::$user->data['user_password']))
						{
							phpbb::$user->reset_login_keys();
							add_log('user', phpbb::$user->data['user_id'], 'LOG_USER_NEW_PASSWORD', $data['username']);
						}

						if (phpbb::$auth->acl_get('u_chgemail') && $data['email'] != phpbb::$user->data['user_email'])
						{
							add_log('user', phpbb::$user->data['user_id'], 'LOG_USER_UPDATE_EMAIL', $data['username'], phpbb::$user->data['user_email'], $data['email']);
						}

						$message = 'PROFILE_UPDATED';

						if (phpbb::$auth->acl_get('u_chgemail') && phpbb::$config['email_enable'] && $data['email'] != phpbb::$user->data['user_email'] && phpbb::$user->data['user_type'] != USER_FOUNDER && (phpbb::$config['require_activation'] == USER_ACTIVATION_SELF || phpbb::$config['require_activation'] == USER_ACTIVATION_ADMIN))
						{
							$message = (phpbb::$config['require_activation'] == USER_ACTIVATION_SELF) ? 'ACCOUNT_EMAIL_CHANGED' : 'ACCOUNT_EMAIL_CHANGED_ADMIN';

							include_once(phpbb::$phpbb_root_path . 'system/includes/functions_messenger.' . phpbb::$phpEx);

							$server_url = generate_board_url();

							$user_actkey = gen_rand_string(mt_rand(6, 10));

							$messenger = new messenger(false);

							$template_file = (phpbb::$config['require_activation'] == USER_ACTIVATION_ADMIN) ? 'user_activate_inactive' : 'user_activate';
							$messenger->template($template_file, phpbb::$user->data['user_lang']);

							$messenger->to($data['email'], $data['username']);

							$messenger->headers('X-AntiAbuse: Board servername - ' . phpbb::$config['server_name']);
							$messenger->headers('X-AntiAbuse: User_id - ' . phpbb::$user->data['user_id']);
							$messenger->headers('X-AntiAbuse: Username - ' . phpbb::$user->data['username']);
							$messenger->headers('X-AntiAbuse: User IP - ' . phpbb::$user->ip);

							$messenger->assign_vars(array(
								'USERNAME'		=> htmlspecialchars_decode($data['username']),
								'U_ACTIVATE'	=> "$server_url/ucp.$phpEx?mode=activate&u={phpbb::$user->data['user_id']}&k=$user_actkey")
							);

							$messenger->send(NOTIFY_EMAIL);

							if (phpbb::$config['require_activation'] == USER_ACTIVATION_ADMIN)
							{
								// Grab an array of user_id's with a_user permissions ... these users can activate a user
								$admin_ary = phpbb::$auth->acl_get_list(false, 'a_user', false);
								$admin_ary = (!empty($admin_ary[0]['a_user'])) ? $admin_ary[0]['a_user'] : array();

								// Also include founders
								$where_sql = ' WHERE user_type = ' . USER_FOUNDER;

								if (sizeof($admin_ary))
								{
									$where_sql .= ' OR ' . phpbb::$db->sql_in_set('user_id', $admin_ary);
								}

								$sql = 'SELECT user_id, username, user_email, user_lang, user_jabber, user_notify_type
									FROM ' . USERS_TABLE . ' ' .
									$where_sql;
								$result = phpbb::$db->sql_query($sql);

								while ($row = phpbb::$db->sql_fetchrow($result))
								{
									$messenger->template('admin_activate', $row['user_lang']);
									$messenger->to($row['user_email'], $row['username']);
									$messenger->im($row['user_jabber'], $row['username']);

									$messenger->assign_vars(array(
										'USERNAME'			=> htmlspecialchars_decode($data['username']),
										'U_USER_DETAILS'	=> "$server_url/memberlist.$phpEx?mode=viewprofile&u={phpbb::$user->data['user_id']}",
										'U_ACTIVATE'		=> "$server_url/ucp.$phpEx?mode=activate&u={phpbb::$user->data['user_id']}&k=$user_actkey")
									);

									$messenger->send($row['user_notify_type']);
								}
								phpbb::$db->sql_freeresult($result);
							}

							user_active_flip('deactivate', phpbb::$user->data['user_id'], INACTIVE_PROFILE);

							// Because we want the profile to be reactivated we set user_newpasswd to empty (else the reactivation will fail)
							$sql_ary['user_actkey'] = $user_actkey;
							$sql_ary['user_newpasswd'] = '';
						}

						if (sizeof($sql_ary))
						{
							$sql = 'UPDATE ' . USERS_TABLE . '
								SET ' . phpbb::$db->sql_build_array('UPDATE', $sql_ary) . '
								WHERE user_id = ' . phpbb::$user->data['user_id'];
							phpbb::$db->sql_query($sql);
						}

						// Need to update config, forum, topic, posting, messages, etc.
						if ($data['username'] != phpbb::$user->data['username'] && phpbb::$auth->acl_get('u_chgname') && phpbb::$config['allow_namechange'])
						{
							user_update_name(phpbb::$user->data['username'], $data['username']);
						}

						// Now, we can remove the user completely (kill the session) - NOT BEFORE!!!
						if (!empty($sql_ary['user_actkey']))
						{
							meta_refresh(5, append_sid(phpbb::$phpbb_root_path . 'index.' . phpbb::$phpEx));
							$message = phpbb::$user->lang[$message] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_INDEX'], '<a href="' . append_sid(phpbb::$phpbb_root_path . 'index.' . phpbb::$phpEx) . '">', '</a>');

							// Because the user gets deactivated we log him out too, killing his session
							phpbb::$user->session_kill();
						}
						else
						{
							meta_refresh(3, $this->u_action);
							$message = phpbb::$user->lang[$message] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');
						}

						trigger_error($message);
					}

					// Replace "error" strings with their real, localised form
					$error = preg_replace('#^([A-Z_]+)$#e', "(!empty(\phpbb::$user->lang['\\1'])) ? \phpbb::$user->lang['\\1'] : '\\1'", $error);
				}

				phpbb::$template->assign_vars(array(
					'ERROR'				=> (sizeof($error)) ? implode('<br />', $error) : '',

					'USERNAME'			=> $data['username'],
					'EMAIL'				=> $data['email'],
					'PASSWORD_CONFIRM'	=> $data['password_confirm'],
					'NEW_PASSWORD'		=> $data['new_password'],
					'CUR_PASSWORD'		=> '',

					'L_USERNAME_EXPLAIN'		=> sprintf(phpbb::$user->lang[phpbb::$config['allow_name_chars'] . '_EXPLAIN'], phpbb::$config['min_name_chars'], phpbb::$config['max_name_chars']),
					'L_CHANGE_PASSWORD_EXPLAIN'	=> sprintf(phpbb::$user->lang[phpbb::$config['pass_complex'] . '_EXPLAIN'], phpbb::$config['min_pass_chars'], phpbb::$config['max_pass_chars']),

					'S_FORCE_PASSWORD'	=> (phpbb::$auth->acl_get('u_chgpasswd') && phpbb::$config['chg_passforce'] && phpbb::$user->data['user_passchg'] < time() - (phpbb::$config['chg_passforce'] * 86400)) ? true : false,
					'S_CHANGE_USERNAME' => (phpbb::$config['allow_namechange'] && phpbb::$auth->acl_get('u_chgname')) ? true : false,
					'S_CHANGE_EMAIL'	=> (phpbb::$auth->acl_get('u_chgemail')) ? true : false,
					'S_CHANGE_PASSWORD'	=> (phpbb::$auth->acl_get('u_chgpasswd')) ? true : false)
				);
			break;

			case 'profile_info':

				include(phpbb::$phpbb_root_path . 'system/core/profile_fields.' . phpbb::$phpEx);

				$cp = new custom_profile();

				$cp_data = $cp_error = array();

				$data = array(
					'icq'			=> request_var('icq', phpbb::$user->data['user_icq']),
					'aim'			=> request_var('aim', phpbb::$user->data['user_aim']),
					'msn'			=> request_var('msn', phpbb::$user->data['user_msnm']),
					'yim'			=> request_var('yim', phpbb::$user->data['user_yim']),
					'jabber'		=> utf8_normalize_nfc(request_var('jabber', phpbb::$user->data['user_jabber'], true)),
					'website'		=> request_var('website', phpbb::$user->data['user_website']),
					'location'		=> utf8_normalize_nfc(request_var('location', phpbb::$user->data['user_from'], true)),
					'occupation'	=> utf8_normalize_nfc(request_var('occupation', phpbb::$user->data['user_occ'], true)),
					'interests'		=> utf8_normalize_nfc(request_var('interests', phpbb::$user->data['user_interests'], true)),
				);

				if (phpbb::$config['allow_birthdays'])
				{
					$data['bday_day'] = $data['bday_month'] = $data['bday_year'] = 0;

					if (phpbb::$user->data['user_birthday'])
					{
						list($data['bday_day'], $data['bday_month'], $data['bday_year']) = explode('-', phpbb::$user->data['user_birthday']);
					}

					$data['bday_day'] = request_var('bday_day', $data['bday_day']);
					$data['bday_month'] = request_var('bday_month', $data['bday_month']);
					$data['bday_year'] = request_var('bday_year', $data['bday_year']);
					$data['user_birthday'] = sprintf('%2d-%2d-%4d', $data['bday_day'], $data['bday_month'], $data['bday_year']);
				}

				add_form_key('ucp_profile_info');

				if ($submit)
				{
					$validate_array = array(
						'icq'			=> array(
							array('string', true, 3, 15),
							array('match', true, '#^[0-9]+$#i')),
						'aim'			=> array('string', true, 3, 255),
						'msn'			=> array('string', true, 5, 255),
						'jabber'		=> array(
							array('string', true, 5, 255),
							array('jabber')),
						'yim'			=> array('string', true, 5, 255),
						'website'		=> array(
							array('string', true, 12, 255),
							array('match', true, '#^http[s]?://(.*?\.)*?[a-z0-9\-]+\.[a-z]{2,4}#i')),
						'location'		=> array('string', true, 2, 100),
						'occupation'	=> array('string', true, 2, 500),
						'interests'		=> array('string', true, 2, 500),
					);

					if (phpbb::$config['allow_birthdays'])
					{
						$validate_array = array_merge($validate_array, array(
							'bday_day'		=> array('num', true, 1, 31),
							'bday_month'	=> array('num', true, 1, 12),
							'bday_year'		=> array('num', true, 1901, gmdate('Y', time()) + 50),
							'user_birthday' => array('date', true),
						));
					}

					$error = validate_data($data, $validate_array);

					// validate custom profile fields
					$cp->submit_cp_field('profile', phpbb::$user->get_iso_lang_id(), $cp_data, $cp_error);

					if (sizeof($cp_error))
					{
						$error = array_merge($error, $cp_error);
					}

					if (!check_form_key('ucp_profile_info'))
					{
						$error[] = 'FORM_INVALID';
					}

					if (!sizeof($error))
					{
						$data['notify'] = phpbb::$user->data['user_notify_type'];

						if ($data['notify'] == NOTIFY_IM && (!phpbb::$config['jab_enable'] || !$data['jabber'] || !@extension_loaded('xml')))
						{
							// User has not filled in a jabber address (Or one of the modules is disabled or jabber is disabled)
							// Disable notify by Jabber now for this user.
							$data['notify'] = NOTIFY_EMAIL;
						}

						$sql_ary = array(
							'user_icq'		=> $data['icq'],
							'user_aim'		=> $data['aim'],
							'user_msnm'		=> $data['msn'],
							'user_yim'		=> $data['yim'],
							'user_jabber'	=> $data['jabber'],
							'user_website'	=> $data['website'],
							'user_from'		=> $data['location'],
							'user_occ'		=> $data['occupation'],
							'user_interests'=> $data['interests'],
							'user_notify_type'	=> $data['notify'],
						);

						if (phpbb::$config['allow_birthdays'])
						{
							$sql_ary['user_birthday'] = $data['user_birthday'];
						}

						$sql = 'UPDATE ' . USERS_TABLE . '
							SET ' . phpbb::$db->sql_build_array('UPDATE', $sql_ary) . '
							WHERE user_id = ' . phpbb::$user->data['user_id'];
						phpbb::$db->sql_query($sql);

						// Update Custom Fields
						$cp->update_profile_field_data(phpbb::$user->data['user_id'], $cp_data);

						meta_refresh(3, $this->u_action);
						$message = phpbb::$user->lang['PROFILE_UPDATED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');
						trigger_error($message);
					}

					// Replace "error" strings with their real, localised form
					$error = preg_replace('#^([A-Z_]+)$#e', "(!empty(\phpbb::$user->lang['\\1'])) ? \phpbb::$user->lang['\\1'] : '\\1'", $error);
				}

				if (phpbb::$config['allow_birthdays'])
				{
					$s_birthday_day_options = '<option value="0"' . ((!$data['bday_day']) ? ' selected="selected"' : '') . '>--</option>';
					for ($i = 1; $i < 32; $i++)
					{
						$selected = ($i == $data['bday_day']) ? ' selected="selected"' : '';
						$s_birthday_day_options .= "<option value=\"$i\"$selected>$i</option>";
					}

					$s_birthday_month_options = '<option value="0"' . ((!$data['bday_month']) ? ' selected="selected"' : '') . '>--</option>';
					for ($i = 1; $i < 13; $i++)
					{
						$selected = ($i == $data['bday_month']) ? ' selected="selected"' : '';
						$s_birthday_month_options .= "<option value=\"$i\"$selected>$i</option>";
					}
					$s_birthday_year_options = '';

					$now = getdate();
					$s_birthday_year_options = '<option value="0"' . ((!$data['bday_year']) ? ' selected="selected"' : '') . '>--</option>';
					for ($i = $now['year'] - 100; $i <= $now['year']; $i++)
					{
						$selected = ($i == $data['bday_year']) ? ' selected="selected"' : '';
						$s_birthday_year_options .= "<option value=\"$i\"$selected>$i</option>";
					}
					unset($now);

					phpbb::$template->assign_vars(array(
						'S_BIRTHDAY_DAY_OPTIONS'	=> $s_birthday_day_options,
						'S_BIRTHDAY_MONTH_OPTIONS'	=> $s_birthday_month_options,
						'S_BIRTHDAY_YEAR_OPTIONS'	=> $s_birthday_year_options,
						'S_BIRTHDAYS_ENABLED'		=> true,
					));
				}

				phpbb::$template->assign_vars(array(
					'ERROR'		=> (sizeof($error)) ? implode('<br />', $error) : '',

					'ICQ'		=> $data['icq'],
					'YIM'		=> $data['yim'],
					'AIM'		=> $data['aim'],
					'MSN'		=> $data['msn'],
					'JABBER'	=> $data['jabber'],
					'WEBSITE'	=> $data['website'],
					'LOCATION'	=> $data['location'],
					'OCCUPATION'=> $data['occupation'],
					'INTERESTS'	=> $data['interests'],
				));

				// Get additional profile fields and assign them to the template block var 'profile_fields'
				phpbb::$user->get_profile_fields(phpbb::$user->data['user_id']);

				$cp->generate_profile_fields('profile', phpbb::$user->get_iso_lang_id());

			break;

			case 'signature':

				if (!phpbb::$auth->acl_get('u_sig'))
				{
					trigger_error('NO_AUTH_SIGNATURE');
				}

				include(phpbb::$phpbb_root_path . 'system/includes/functions_posting.' . phpbb::$phpEx);
				include(phpbb::$phpbb_root_path . 'system/includes/functions_display.' . phpbb::$phpEx);

				$enable_bbcode	= (phpbb::$config['allow_sig_bbcode']) ? (bool) phpbb::$user->optionget('sig_bbcode') : false;
				$enable_smilies	= (phpbb::$config['allow_sig_smilies']) ? (bool) phpbb::$user->optionget('sig_smilies') : false;
				$enable_urls	= (phpbb::$config['allow_sig_links']) ? (bool) phpbb::$user->optionget('sig_links') : false;

				$signature		= utf8_normalize_nfc(request_var('signature', (string) phpbb::$user->data['user_sig'], true));

				add_form_key('ucp_sig');

				if ($submit || $preview)
				{
					include(phpbb::$phpbb_root_path . 'system/includes/message_parser.' . phpbb::$phpEx);

					$enable_bbcode	= (phpbb::$config['allow_sig_bbcode']) ? ((request_var('disable_bbcode', false)) ? false : true) : false;
					$enable_smilies	= (phpbb::$config['allow_sig_smilies']) ? ((request_var('disable_smilies', false)) ? false : true) : false;
					$enable_urls	= (phpbb::$config['allow_sig_links']) ? ((request_var('disable_magic_url', false)) ? false : true) : false;

					if (!sizeof($error))
					{
						$message_parser = new parse_message($signature);

						// Allowing Quote BBCode
						$message_parser->parse($enable_bbcode, $enable_urls, $enable_smilies, phpbb::$config['allow_sig_img'], phpbb::$config['allow_sig_flash'], true, phpbb::$config['allow_sig_links'], true, 'sig');

						if (sizeof($message_parser->warn_msg))
						{
							$error[] = implode('<br />', $message_parser->warn_msg);
						}

						if (!check_form_key('ucp_sig'))
						{
							$error[] = 'FORM_INVALID';
						}

						if (!sizeof($error) && $submit)
						{
							phpbb::$user->optionset('sig_bbcode', $enable_bbcode);
							phpbb::$user->optionset('sig_smilies', $enable_smilies);
							phpbb::$user->optionset('sig_links', $enable_urls);

							$sql_ary = array(
								'user_sig'					=> (string) $message_parser->message,
								'user_options'				=> phpbb::$user->data['user_options'],
								'user_sig_bbcode_uid'		=> (string) $message_parser->bbcode_uid,
								'user_sig_bbcode_bitfield'	=> $message_parser->bbcode_bitfield
							);

							$sql = 'UPDATE ' . USERS_TABLE . '
								SET ' . phpbb::$db->sql_build_array('UPDATE', $sql_ary) . '
								WHERE user_id = ' . phpbb::$user->data['user_id'];
							phpbb::$db->sql_query($sql);

							$message = phpbb::$user->lang['PROFILE_UPDATED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');
							trigger_error($message);
						}
					}

					// Replace "error" strings with their real, localised form
					$error = preg_replace('#^([A-Z_]+)$#e', "(!empty(\phpbb::$user->lang['\\1'])) ? \phpbb::$user->lang['\\1'] : '\\1'", $error);
				}

				$signature_preview = '';
				if ($preview)
				{
					// Now parse it for displaying
					$signature_preview = $message_parser->format_display($enable_bbcode, $enable_urls, $enable_smilies, false);
					unset($message_parser);
				}

				decode_message($signature, phpbb::$user->data['user_sig_bbcode_uid']);

				phpbb::$template->assign_vars(array(
					'ERROR'				=> (sizeof($error)) ? implode('<br />', $error) : '',
					'SIGNATURE'			=> $signature,
					'SIGNATURE_PREVIEW'	=> $signature_preview,

					'S_BBCODE_CHECKED' 		=> (!$enable_bbcode) ? ' checked="checked"' : '',
					'S_SMILIES_CHECKED' 	=> (!$enable_smilies) ? ' checked="checked"' : '',
					'S_MAGIC_URL_CHECKED' 	=> (!$enable_urls) ? ' checked="checked"' : '',

					'BBCODE_STATUS'			=> (phpbb::$config['allow_sig_bbcode']) ? sprintf(phpbb::$user->lang['BBCODE_IS_ON'], '<a href="' . append_sid(phpbb::$phpbb_root_path . "faq." . phpbb::$phpEx, 'mode=bbcode') . '">', '</a>') : sprintf(phpbb::$user->lang['BBCODE_IS_OFF'], '<a href="' . append_sid(phpbb::$phpbb_root_path . "faq." . phpbb::$phpEx, 'mode=bbcode') . '">', '</a>'),
					'SMILIES_STATUS'		=> (phpbb::$config['allow_sig_smilies']) ? phpbb::$user->lang['SMILIES_ARE_ON'] : phpbb::$user->lang['SMILIES_ARE_OFF'],
					'IMG_STATUS'			=> (phpbb::$config['allow_sig_img']) ? phpbb::$user->lang['IMAGES_ARE_ON'] : phpbb::$user->lang['IMAGES_ARE_OFF'],
					'FLASH_STATUS'			=> (phpbb::$config['allow_sig_flash']) ? phpbb::$user->lang['FLASH_IS_ON'] : phpbb::$user->lang['FLASH_IS_OFF'],
					'URL_STATUS'			=> (phpbb::$config['allow_sig_links']) ? phpbb::$user->lang['URL_IS_ON'] : phpbb::$user->lang['URL_IS_OFF'],
					'MAX_FONT_SIZE'			=> (int) phpbb::$config['max_sig_font_size'],

					'L_SIGNATURE_EXPLAIN'	=> sprintf(phpbb::$user->lang['SIGNATURE_EXPLAIN'], phpbb::$config['max_sig_chars']),

					'S_BBCODE_ALLOWED'		=> phpbb::$config['allow_sig_bbcode'],
					'S_SMILIES_ALLOWED'		=> phpbb::$config['allow_sig_smilies'],
					'S_BBCODE_IMG'			=> (phpbb::$config['allow_sig_img']) ? true : false,
					'S_BBCODE_FLASH'		=> (phpbb::$config['allow_sig_flash']) ? true : false,
					'S_LINKS_ALLOWED'		=> (phpbb::$config['allow_sig_links']) ? true : false)
				);

				// Build custom bbcodes array
				display_custom_bbcodes();

			break;

			case 'avatar':

				include(phpbb::$phpbb_root_path . 'system/includes/functions_display.' . phpbb::$phpEx);

				$display_gallery = request_var('display_gallery', '0');
				$avatar_select = basename(request_var('avatar_select', ''));
				$category = basename(request_var('category', ''));

				$can_upload = (file_exists(phpbb::$phpbb_root_path . phpbb::$config['avatar_path']) && phpbb_is_writable(phpbb::$phpbb_root_path . phpbb::$config['avatar_path']) && phpbb::$auth->acl_get('u_chgavatar') && (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on')) ? true : false;

				add_form_key('ucp_avatar');

				if ($submit)
				{
					if (check_form_key('ucp_avatar'))
					{
						if (avatar_process_user($error, false, $can_upload))
						{
							meta_refresh(3, $this->u_action);
							$message = phpbb::$user->lang['PROFILE_UPDATED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');
							trigger_error($message);
						}
					}
					else
					{
						$error[] = 'FORM_INVALID';
					}
					// Replace "error" strings with their real, localised form
					$error = preg_replace('#^([A-Z_]+)$#e', "(!empty(\phpbb::$user->lang['\\1'])) ? \phpbb::$user->lang['\\1'] : '\\1'", $error);
				}

				if (!phpbb::$config['allow_avatar'] && phpbb::$user->data['user_avatar_type'])
				{
					$error[] = phpbb::$user->lang['AVATAR_NOT_ALLOWED'];
				}
				else if (((phpbb::$user->data['user_avatar_type'] == AVATAR_UPLOAD) && !phpbb::$config['allow_avatar_upload']) ||
				 ((phpbb::$user->data['user_avatar_type'] == AVATAR_REMOTE) && !phpbb::$config['allow_avatar_remote']) ||
				 ((phpbb::$user->data['user_avatar_type'] == AVATAR_GALLERY) && !phpbb::$config['allow_avatar_local']))
				{
					$error[] = phpbb::$user->lang['AVATAR_TYPE_NOT_ALLOWED'];
				}

				phpbb::$template->assign_vars(array(
					'ERROR'			=> (sizeof($error)) ? implode('<br />', $error) : '',
					'AVATAR'		=> get_user_avatar(phpbb::$user->data['user_avatar'], phpbb::$user->data['user_avatar_type'], phpbb::$user->data['user_avatar_width'], phpbb::$user->data['user_avatar_height'], 'USER_AVATAR', true),
					'AVATAR_SIZE'	=> phpbb::$config['avatar_filesize'],

					'U_GALLERY'		=> append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'i=profile&amp;mode=avatar&amp;display_gallery=1'),

					'S_FORM_ENCTYPE'	=> ($can_upload && (phpbb::$config['allow_avatar_upload'] || phpbb::$config['allow_avatar_remote_upload'])) ? ' enctype="multipart/form-data"' : '',

					'L_AVATAR_EXPLAIN'	=> sprintf(phpbb::$user->lang['AVATAR_EXPLAIN'], phpbb::$config['avatar_max_width'], phpbb::$config['avatar_max_height'], phpbb::$config['avatar_filesize'] / 1024),
				));

				if (phpbb::$config['allow_avatar'] && $display_gallery && phpbb::$auth->acl_get('u_chgavatar') && phpbb::$config['allow_avatar_local'])
				{
					avatar_gallery($category, $avatar_select, 4);
				}
				else if (phpbb::$config['allow_avatar'])
				{
					$avatars_enabled = (($can_upload && (phpbb::$config['allow_avatar_upload'] || phpbb::$config['allow_avatar_remote_upload'])) || (phpbb::$auth->acl_get('u_chgavatar') && (phpbb::$config['allow_avatar_local'] || phpbb::$config['allow_avatar_remote']))) ? true : false;

					phpbb::$template->assign_vars(array(
						'AVATAR_WIDTH'	=> request_var('width', phpbb::$user->data['user_avatar_width']),
						'AVATAR_HEIGHT'	=> request_var('height', phpbb::$user->data['user_avatar_height']),

						'S_AVATARS_ENABLED'		=> $avatars_enabled,
						'S_UPLOAD_AVATAR_FILE'	=> ($can_upload && phpbb::$config['allow_avatar_upload']) ? true : false,
						'S_UPLOAD_AVATAR_URL'	=> ($can_upload && phpbb::$config['allow_avatar_remote_upload']) ? true : false,
						'S_LINK_AVATAR'			=> (phpbb::$auth->acl_get('u_chgavatar') && phpbb::$config['allow_avatar_remote']) ? true : false,
						'S_DISPLAY_GALLERY'		=> (phpbb::$auth->acl_get('u_chgavatar') && phpbb::$config['allow_avatar_local']) ? true : false)
					);
				}

			break;
		}

		phpbb::$template->assign_vars(array(
			'L_TITLE'	=> phpbb::$user->lang['UCP_PROFILE_' . strtoupper($mode)],

			'S_HIDDEN_FIELDS'	=> $s_hidden_fields,
			'S_UCP_ACTION'		=> $this->u_action)
		);

		// Set desired template
		$this->tpl_name = 'ucp_profile_' . $mode;
		$this->page_title = 'UCP_PROFILE_' . strtoupper($mode);
	}
}

?>