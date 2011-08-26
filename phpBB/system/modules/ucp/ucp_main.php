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
* ucp_main
* UCP Front Panel
* @package ucp
*/
class ucp_main
{
	var $p_master;
	var $u_action;

	function ucp_main(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	function main($id, $mode)
	{

		switch ($mode)
		{
			case 'front':

				phpbb::$user->add_lang('memberlist');

				$sql_from = TOPICS_TABLE . ' t ';
				$sql_select = '';

				if (phpbb::$config['load_db_track'])
				{
					$sql_from .= ' LEFT JOIN ' . TOPICS_POSTED_TABLE . ' tp ON (tp.topic_id = t.topic_id
						AND tp.user_id = ' . phpbb::$user->data['user_id'] . ')';
					$sql_select .= ', tp.topic_posted';
				}

				if (phpbb::$config['load_db_lastread'])
				{
					$sql_from .= ' LEFT JOIN ' . TOPICS_TRACK_TABLE . ' tt ON (tt.topic_id = t.topic_id
						AND tt.user_id = ' . phpbb::$user->data['user_id'] . ')';
					$sql_select .= ', tt.mark_time';
				}

				$topic_type = phpbb::$user->lang['VIEW_TOPIC_GLOBAL'];
				$folder = 'global_read';
				$folder_new = 'global_unread';

				// Get cleaned up list... return only those forums not having the f_read permission
				$forum_ary = phpbb::$auth->acl_getf('!f_read', true);
				$forum_ary = array_unique(array_keys($forum_ary));

				// Determine first forum the user is able to read into - for global announcement link
				$sql = 'SELECT forum_id
					FROM ' . FORUMS_TABLE . '
					WHERE forum_type = ' . FORUM_POST;

				if (sizeof($forum_ary))
				{
					$sql .= ' AND ' . phpbb::$db->sql_in_set('forum_id', $forum_ary, true);
				}
				$result = phpbb::$db->sql_query_limit($sql, 1);
				$g_forum_id = (int) phpbb::$db->sql_fetchfield('forum_id');
				phpbb::$db->sql_freeresult($result);

				$sql = "SELECT t.* $sql_select
					FROM $sql_from
					WHERE t.forum_id = 0
						AND t.topic_type = " . POST_GLOBAL . '
					ORDER BY t.topic_last_post_time DESC';

				$topic_list = $rowset = array();
				// If the user can't see any forums, he can't read any posts because fid of 0 is invalid
				if ($g_forum_id)
				{
					$result = phpbb::$db->sql_query($sql);

					while ($row = phpbb::$db->sql_fetchrow($result))
					{
						$topic_list[] = $row['topic_id'];
						$rowset[$row['topic_id']] = $row;
					}
					phpbb::$db->sql_freeresult($result);
				}

				$topic_tracking_info = array();
				if (phpbb::$config['load_db_lastread'])
				{
					$topic_tracking_info = get_topic_tracking(0, $topic_list, $rowset, false, $topic_list);
				}
				else
				{
					$topic_tracking_info = get_complete_topic_tracking(0, $topic_list, $topic_list);
				}

				foreach ($topic_list as $topic_id)
				{
					$row = &$rowset[$topic_id];

					$forum_id = $row['forum_id'];
					$topic_id = $row['topic_id'];

					$unread_topic = (isset($topic_tracking_info[$topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;

					$folder_img = ($unread_topic) ? $folder_new : $folder;
					$folder_alt = ($unread_topic) ? 'UNREAD_POSTS' : (($row['topic_status'] == ITEM_LOCKED) ? 'TOPIC_LOCKED' : 'NO_UNREAD_POSTS');

					if ($row['topic_status'] == ITEM_LOCKED)
					{
						$folder_img .= '_locked';
					}

					// Posted image?
					if (!empty($row['topic_posted']) && $row['topic_posted'])
					{
						$folder_img .= '_mine';
					}

					phpbb::$template->assign_block_vars('topicrow', array(
						'FORUM_ID'					=> $forum_id,
						'TOPIC_ID'					=> $topic_id,
						'TOPIC_AUTHOR'				=> get_username_string('username', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
						'TOPIC_AUTHOR_COLOUR'		=> get_username_string('colour', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
						'TOPIC_AUTHOR_FULL'			=> get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
						'FIRST_POST_TIME'			=> phpbb::$user->format_date($row['topic_time']),
						'LAST_POST_SUBJECT'			=> censor_text($row['topic_last_post_subject']),
						'LAST_POST_TIME'			=> phpbb::$user->format_date($row['topic_last_post_time']),
						'LAST_VIEW_TIME'			=> phpbb::$user->format_date($row['topic_last_view_time']),
						'LAST_POST_AUTHOR'			=> get_username_string('username', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
						'LAST_POST_AUTHOR_COLOUR'	=> get_username_string('colour', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
						'LAST_POST_AUTHOR_FULL'		=> get_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
						'TOPIC_TITLE'				=> censor_text($row['topic_title']),
						'TOPIC_TYPE'				=> $topic_type,

						'TOPIC_FOLDER_IMG'		=> phpbb::$user->img($folder_img, $folder_alt),
						'TOPIC_FOLDER_IMG_SRC'	=> phpbb::$user->img($folder_img, $folder_alt, false, '', 'src'),
						'ATTACH_ICON_IMG'		=> (phpbb::$auth->acl_get('u_download') && phpbb::$auth->acl_get('f_download', $forum_id) && $row['topic_attachment']) ? phpbb::$user->img('icon_topic_attach', '') : '',

						'S_USER_POSTED'		=> (!empty($row['topic_posted']) && $row['topic_posted']) ? true : false,
						'S_UNREAD'			=> $unread_topic,

						'U_TOPIC_AUTHOR'		=> get_username_string('profile', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
						'U_LAST_POST'			=> append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, "f=$g_forum_id&amp;t=$topic_id&amp;p=" . $row['topic_last_post_id']) . '#p' . $row['topic_last_post_id'],
						'U_LAST_POST_AUTHOR'	=> get_username_string('profile', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
						'U_NEWEST_POST'			=> append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, "f=$g_forum_id&amp;t=$topic_id&amp;view=unread") . '#unread',
						'U_VIEW_TOPIC'			=> append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, "f=$g_forum_id&amp;t=$topic_id"))
					);
				}

				if (phpbb::$config['load_user_activity'])
				{
					if (!function_exists('display_user_activity'))
					{
						include_once(phpbb::$phpbb_root_path . 'system/includes/functions_display.' . phpbb::$phpEx);
					}
					display_user_activity(phpbb::$user->data);
				}

				// Do the relevant calculations
				$memberdays = max(1, round((time() - phpbb::$user->data['user_regdate']) / 86400));
				$posts_per_day = phpbb::$user->data['user_posts'] / $memberdays;
				$percentage = (phpbb::$config['num_posts']) ? min(100, (phpbb::$user->data['user_posts'] / phpbb::$config['num_posts']) * 100) : 0;

				phpbb::$template->assign_vars(array(
					'USER_COLOR'		=> (!empty(phpbb::$user->data['user_colour'])) ? phpbb::$user->data['user_colour'] : '',
					'JOINED'			=> phpbb::$user->format_date(phpbb::$user->data['user_regdate']),
					'VISITED'			=> (empty($last_visit)) ? ' - ' : phpbb::$user->format_date($last_visit),
					'WARNINGS'			=> (phpbb::$user->data['user_warnings']) ? phpbb::$user->data['user_warnings'] : 0,
					'POSTS'				=> (phpbb::$user->data['user_posts']) ? phpbb::$user->data['user_posts'] : 0,
					'POSTS_DAY'			=> sprintf(phpbb::$user->lang['POST_DAY'], $posts_per_day),
					'POSTS_PCT'			=> sprintf(phpbb::$user->lang['POST_PCT'], $percentage),

					'OCCUPATION'	=> (!empty($row['user_occ'])) ? $row['user_occ'] : '',
					'INTERESTS'		=> (!empty($row['user_interests'])) ? $row['user_interests'] : '',

//					'S_GROUP_OPTIONS'	=> $group_options,

					'U_SEARCH_USER'		=> (phpbb::$auth->acl_get('u_search')) ? append_sid(phpbb::$phpbb_root_path . "search." . phpbb::$phpEx, 'author_id=' . phpbb::$user->data['user_id'] . '&amp;sr=posts') : '',
				));

			break;

			case 'subscribed':

				include(phpbb::$phpbb_root_path . 'system/includes/functions_display.' . phpbb::$phpEx);

				phpbb::$user->add_lang('viewforum');

				add_form_key('ucp_front_subscribed');

				$unwatch = (isset($_POST['unwatch'])) ? true : false;

				if ($unwatch)
				{
					if (check_form_key('ucp_front_subscribed'))
					{
						$forums = array_keys(request_var('f', array(0 => 0)));
						$topics = array_keys(request_var('t', array(0 => 0)));
						$msg = '';

						if (sizeof($forums) || sizeof($topics))
						{
							$l_unwatch = '';
							if (sizeof($forums))
							{
								$sql = 'DELETE FROM ' . FORUMS_WATCH_TABLE . '
									WHERE ' . phpbb::$db->sql_in_set('forum_id', $forums) . '
										AND user_id = ' . phpbb::$user->data['user_id'];
								phpbb::$db->sql_query($sql);

								$l_unwatch .= '_FORUMS';
							}

							if (sizeof($topics))
							{
								$sql = 'DELETE FROM ' . TOPICS_WATCH_TABLE . '
									WHERE ' . phpbb::$db->sql_in_set('topic_id', $topics) . '
										AND user_id = ' . phpbb::$user->data['user_id'];
								phpbb::$db->sql_query($sql);

								$l_unwatch .= '_TOPICS';
							}
							$msg = phpbb::$user->lang['UNWATCHED' . $l_unwatch];
						}
						else
						{
							$msg = phpbb::$user->lang['NO_WATCHED_SELECTED'];
						}
					}
					else
					{
						$msg = phpbb::$user->lang['FORM_INVALID'];
					}
					$message = $msg . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_UCP'], '<a href="' . append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, "i=$id&amp;mode=subscribed") . '">', '</a>');
					meta_refresh(3, append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, "i=$id&amp;mode=subscribed"));
					trigger_error($message);
				}

				$forbidden_forums = array();

				if (phpbb::$config['allow_forum_notify'])
				{
					$forbidden_forums = phpbb::$auth->acl_getf('!f_read', true);
					$forbidden_forums = array_unique(array_keys($forbidden_forums));

					$sql_array = array(
						'SELECT'	=> 'f.*',

						'FROM'		=> array(
							FORUMS_WATCH_TABLE	=> 'fw',
							FORUMS_TABLE		=> 'f'
						),

						'WHERE'		=> 'fw.user_id = ' . phpbb::$user->data['user_id'] . '
							AND f.forum_id = fw.forum_id
							AND ' . phpbb::$db->sql_in_set('f.forum_id', $forbidden_forums, true, true),

						'ORDER_BY'	=> 'left_id'
					);

					if (phpbb::$config['load_db_lastread'])
					{
						$sql_array['LEFT_JOIN'] = array(
							array(
								'FROM'	=> array(FORUMS_TRACK_TABLE => 'ft'),
								'ON'	=> 'ft.user_id = ' . phpbb::$user->data['user_id'] . ' AND ft.forum_id = f.forum_id'
							)
						);

						$sql_array['SELECT'] .= ', ft.mark_time ';
					}
					else
					{
						$tracking_topics = (isset($_COOKIE[phpbb::$config['cookie_name'] . '_track'])) ? ((STRIP) ? stripslashes($_COOKIE[phpbb::$config['cookie_name'] . '_track']) : $_COOKIE[phpbb::$config['cookie_name'] . '_track']) : '';
						$tracking_topics = ($tracking_topics) ? tracking_unserialize($tracking_topics) : array();
					}

					$sql = phpbb::$db->sql_build_query('SELECT', $sql_array);
					$result = phpbb::$db->sql_query($sql);

					while ($row = phpbb::$db->sql_fetchrow($result))
					{
						$forum_id = $row['forum_id'];

						if (phpbb::$config['load_db_lastread'])
						{
							$forum_check = (!empty($row['mark_time'])) ? $row['mark_time'] : phpbb::$user->data['user_lastmark'];
						}
						else
						{
							$forum_check = (isset($tracking_topics['f'][$forum_id])) ? (int) (base_convert($tracking_topics['f'][$forum_id], 36, 10) + phpbb::$config['board_startdate']) : phpbb::$user->data['user_lastmark'];
						}

						$unread_forum = ($row['forum_last_post_time'] > $forum_check) ? true : false;

						// Which folder should we display?
						if ($row['forum_status'] == ITEM_LOCKED)
						{
							$folder_image = ($unread_forum) ? 'forum_unread_locked' : 'forum_read_locked';
							$folder_alt = 'FORUM_LOCKED';
						}
						else
						{
							$folder_image = ($unread_forum) ? 'forum_unread' : 'forum_read';
							$folder_alt = ($unread_forum) ? 'UNREAD_POSTS' : 'NO_UNREAD_POSTS';
						}

						// Create last post link information, if appropriate
						if ($row['forum_last_post_id'])
						{
							$last_post_time = phpbb::$user->format_date($row['forum_last_post_time']);
							$last_post_url = append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, "f=$forum_id&amp;p=" . $row['forum_last_post_id']) . '#p' . $row['forum_last_post_id'];
						}
						else
						{
							$last_post_time = $last_post_url = '';
						}

						phpbb::$template->assign_block_vars('forumrow', array(
							'FORUM_ID'				=> $forum_id,
							'FORUM_FOLDER_IMG'		=> phpbb::$user->img($folder_image, $folder_alt),
							'FORUM_FOLDER_IMG_SRC'	=> phpbb::$user->img($folder_image, $folder_alt, false, '', 'src'),
							'FORUM_IMAGE'			=> ($row['forum_image']) ? '<img src="' . phpbb::$phpbb_root_path . $row['forum_image'] . '" alt="' . phpbb::$user->lang[$folder_alt] . '" />' : '',
							'FORUM_IMAGE_SRC'		=> ($row['forum_image']) ? phpbb::$phpbb_root_path . $row['forum_image'] : '',
							'FORUM_NAME'			=> $row['forum_name'],
							'FORUM_DESC'			=> generate_text_for_display($row['forum_desc'], $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']),
							'LAST_POST_SUBJECT'		=> $row['forum_last_post_subject'],
							'LAST_POST_TIME'		=> $last_post_time,

							'LAST_POST_AUTHOR'			=> get_username_string('username', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
							'LAST_POST_AUTHOR_COLOUR'	=> get_username_string('colour', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
							'LAST_POST_AUTHOR_FULL'		=> get_username_string('full', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
							'U_LAST_POST_AUTHOR'		=> get_username_string('profile', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),

							'U_LAST_POST'			=> $last_post_url,
							'U_VIEWFORUM'			=> append_sid(phpbb::$phpbb_root_path . "viewforum." . phpbb::$phpEx, 'f=' . $row['forum_id']))
						);
					}
					phpbb::$db->sql_freeresult($result);
				}

				// Subscribed Topics
				if (phpbb::$config['allow_topic_notify'])
				{
					if (empty($forbidden_forums))
					{
						$forbidden_forums = phpbb::$auth->acl_getf('!f_read', true);
						$forbidden_forums = array_unique(array_keys($forbidden_forums));
					}
					$this->assign_topiclist('subscribed', $forbidden_forums);
				}

				phpbb::$template->assign_vars(array(
					'S_TOPIC_NOTIFY'		=> phpbb::$config['allow_topic_notify'],
					'S_FORUM_NOTIFY'		=> phpbb::$config['allow_forum_notify'],
				));

			break;

			case 'bookmarks':

				if (!phpbb::$config['allow_bookmarks'])
				{
					phpbb::$template->assign_vars(array(
						'S_NO_DISPLAY_BOOKMARKS'	=> true)
					);
					break;
				}

				include(phpbb::$phpbb_root_path . 'system/includes/functions_display.' . phpbb::$phpEx);

				phpbb::$user->add_lang('viewforum');

				if (isset($_POST['unbookmark']))
				{
					$s_hidden_fields = array('unbookmark' => 1);
					$topics = (isset($_POST['t'])) ? array_keys(request_var('t', array(0 => 0))) : array();
					$url = $this->u_action;

					if (!sizeof($topics))
					{
						trigger_error('NO_BOOKMARKS_SELECTED');
					}

					foreach ($topics as $topic_id)
					{
						$s_hidden_fields['t'][$topic_id] = 1;
					}

					if (confirm_box(true))
					{
						$sql = 'DELETE FROM ' . BOOKMARKS_TABLE . '
							WHERE user_id = ' . phpbb::$user->data['user_id'] . '
								AND ' . phpbb::$db->sql_in_set('topic_id', $topics);
						phpbb::$db->sql_query($sql);

						meta_refresh(3, $url);
						$message = phpbb::$user->lang['BOOKMARKS_REMOVED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_UCP'], '<a href="' . $url . '">', '</a>');
						trigger_error($message);
					}
					else
					{
						confirm_box(false, 'REMOVE_SELECTED_BOOKMARKS', build_hidden_fields($s_hidden_fields));
					}
				}
				$forbidden_forums = phpbb::$auth->acl_getf('!f_read', true);
				$forbidden_forums = array_unique(array_keys($forbidden_forums));

				$this->assign_topiclist('bookmarks', $forbidden_forums);

			break;

			case 'drafts':

				$pm_drafts = ($this->p_master->p_name == 'pm') ? true : false;
				phpbb::$template->assign_var('S_SHOW_DRAFTS', true);

				phpbb::$user->add_lang('posting');

				$edit		= (isset($_REQUEST['edit'])) ? true : false;
				$submit		= (isset($_POST['submit'])) ? true : false;
				$draft_id	= ($edit) ? intval($_REQUEST['edit']) : 0;
				$delete		= (isset($_POST['delete'])) ? true : false;

				$s_hidden_fields = ($edit) ? '<input type="hidden" name="edit" value="' . $draft_id . '" />' : '';
				$draft_subject = $draft_message = '';
				add_form_key('ucp_draft');

				if ($delete)
				{
					if (check_form_key('ucp_draft'))
					{
						$drafts = array_keys(request_var('d', array(0 => 0)));

						if (sizeof($drafts))
						{
							$sql = 'DELETE FROM ' . DRAFTS_TABLE . '
								WHERE ' . phpbb::$db->sql_in_set('draft_id', $drafts) . '
									AND user_id = ' . phpbb::$user->data['user_id'];
							phpbb::$db->sql_query($sql);
						}
						$msg = phpbb::$user->lang['DRAFTS_DELETED'];
						unset($drafts);
					}
					else
					{
						$msg = phpbb::$user->lang['FORM_INVALID'];
					}
					$message = $msg . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');
					meta_refresh(3, $this->u_action);
					trigger_error($message);
				}

				if ($submit && $edit)
				{
					$draft_subject = utf8_normalize_nfc(request_var('subject', '', true));
					$draft_message = utf8_normalize_nfc(request_var('message', '', true));
					if (check_form_key('ucp_draft'))
					{
						if ($draft_message && $draft_subject)
						{
							$draft_row = array(
								'draft_subject' => $draft_subject,
								'draft_message' => $draft_message
							);

							$sql = 'UPDATE ' . DRAFTS_TABLE . '
								SET ' . phpbb::$db->sql_build_array('UPDATE', $draft_row) . "
								WHERE draft_id = $draft_id
									AND user_id = " . phpbb::$user->data['user_id'];
							phpbb::$db->sql_query($sql);

							$message = phpbb::$user->lang['DRAFT_UPDATED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');

							meta_refresh(3, $this->u_action);
							trigger_error($message);
						}
						else
						{
							phpbb::$template->assign_var('ERROR', ($draft_message == '') ? phpbb::$user->lang['EMPTY_DRAFT'] : (($draft_subject == '') ? phpbb::$user->lang['EMPTY_DRAFT_TITLE'] : ''));
						}
					}
					else
					{
						phpbb::$template->assign_var('ERROR', phpbb::$user->lang['FORM_INVALID']);
					}
				}

				if (!$pm_drafts)
				{
					$sql = 'SELECT d.*, f.forum_name
						FROM ' . DRAFTS_TABLE . ' d, ' . FORUMS_TABLE . ' f
						WHERE d.user_id = ' . phpbb::$user->data['user_id'] . ' ' .
							(($edit) ? "AND d.draft_id = $draft_id" : '') . '
							AND f.forum_id = d.forum_id
						ORDER BY d.save_time DESC';
				}
				else
				{
					$sql = 'SELECT * FROM ' . DRAFTS_TABLE . '
						WHERE user_id = ' . phpbb::$user->data['user_id'] . ' ' .
							(($edit) ? "AND draft_id = $draft_id" : '') . '
							AND forum_id = 0
							AND topic_id = 0
						ORDER BY save_time DESC';
				}
				$result = phpbb::$db->sql_query($sql);

				$draftrows = $topic_ids = array();

				while ($row = phpbb::$db->sql_fetchrow($result))
				{
					if ($row['topic_id'])
					{
						$topic_ids[] = (int) $row['topic_id'];
					}
					$draftrows[] = $row;
				}
				phpbb::$db->sql_freeresult($result);

				if (sizeof($topic_ids))
				{
					$sql = 'SELECT topic_id, forum_id, topic_title
						FROM ' . TOPICS_TABLE . '
						WHERE ' . phpbb::$db->sql_in_set('topic_id', array_unique($topic_ids));
					$result = phpbb::$db->sql_query($sql);

					while ($row = phpbb::$db->sql_fetchrow($result))
					{
						$topic_rows[$row['topic_id']] = $row;
					}
					phpbb::$db->sql_freeresult($result);
				}
				unset($topic_ids);

				phpbb::$template->assign_var('S_EDIT_DRAFT', $edit);

				$row_count = 0;
				foreach ($draftrows as $draft)
				{
					$link_topic = $link_forum = $link_pm = false;
					$insert_url = $view_url = $title = '';

					if (isset($topic_rows[$draft['topic_id']]) && phpbb::$auth->acl_get('f_read', $topic_rows[$draft['topic_id']]['forum_id']))
					{
						$link_topic = true;
						$view_url = append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, 'f=' . $topic_rows[$draft['topic_id']]['forum_id'] . '&amp;t=' . $draft['topic_id']);
						$title = $topic_rows[$draft['topic_id']]['topic_title'];

						$insert_url = append_sid(phpbb::$phpbb_root_path . "posting." . phpbb::$phpEx, 'f=' . $topic_rows[$draft['topic_id']]['forum_id'] . '&amp;t=' . $draft['topic_id'] . '&amp;mode=reply&amp;d=' . $draft['draft_id']);
					}
					else if (phpbb::$auth->acl_get('f_read', $draft['forum_id']))
					{
						$link_forum = true;
						$view_url = append_sid(phpbb::$phpbb_root_path . "viewforum." . phpbb::$phpEx, 'f=' . $draft['forum_id']);
						$title = $draft['forum_name'];

						$insert_url = append_sid(phpbb::$phpbb_root_path . "posting." . phpbb::$phpEx, 'f=' . $draft['forum_id'] . '&amp;mode=post&amp;d=' . $draft['draft_id']);
					}
					else if ($pm_drafts)
					{
						$link_pm = true;
						$insert_url = append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, "i=$id&amp;mode=compose&amp;d=" . $draft['draft_id']);
					}

					$template_row = array(
						'DATE'			=> phpbb::$user->format_date($draft['save_time']),
						'DRAFT_MESSAGE'	=> ($submit) ? $draft_message : $draft['draft_message'],
						'DRAFT_SUBJECT'	=> ($submit) ? $draft_subject : $draft['draft_subject'],
						'TITLE'			=> $title,

						'DRAFT_ID'	=> $draft['draft_id'],
						'FORUM_ID'	=> $draft['forum_id'],
						'TOPIC_ID'	=> $draft['topic_id'],

						'U_VIEW'		=> $view_url,
						'U_VIEW_EDIT'	=> $this->u_action . '&amp;edit=' . $draft['draft_id'],
						'U_INSERT'		=> $insert_url,

						'S_LINK_TOPIC'		=> $link_topic,
						'S_LINK_FORUM'		=> $link_forum,
						'S_LINK_PM'			=> $link_pm,
						'S_HIDDEN_FIELDS'	=> $s_hidden_fields
					);
					$row_count++;

					($edit) ? phpbb::$template->assign_vars($template_row) : phpbb::$template->assign_block_vars('draftrow', $template_row);
				}

				if (!$edit)
				{
					phpbb::$template->assign_var('S_DRAFT_ROWS', $row_count);
				}

			break;
		}


		phpbb::$template->assign_vars(array(
			'L_TITLE'			=> phpbb::$user->lang['UCP_MAIN_' . strtoupper($mode)],

			'S_DISPLAY_MARK_ALL'	=> ($mode == 'watched' || ($mode == 'drafts' && !isset($_GET['edit']))) ? true : false,
			'S_HIDDEN_FIELDS'		=> (isset($s_hidden_fields)) ? $s_hidden_fields : '',
			'S_UCP_ACTION'			=> $this->u_action,

			'LAST_POST_IMG'			=> phpbb::$user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
			'NEWEST_POST_IMG'		=> phpbb::$user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
		));

		// Set desired template
		$this->tpl_name = 'ucp_main_' . $mode;
		$this->page_title = 'UCP_MAIN_' . strtoupper($mode);
	}

	/**
	* Build and assign topiclist for bookmarks/subscribed topics
	*/
	function assign_topiclist($mode = 'subscribed', $forbidden_forum_ary = array())
	{

		$table = ($mode == 'subscribed') ? TOPICS_WATCH_TABLE : BOOKMARKS_TABLE;
		$start = request_var('start', 0);

		// Grab icons
		$icons = phpbb::$cache->obtain_icons();

		$sql_array = array(
			'SELECT'	=> 'COUNT(t.topic_id) as topics_count',

			'FROM'		=> array(
				$table			=> 'i',
				TOPICS_TABLE	=> 't'
			),

			'WHERE'		=>	'i.topic_id = t.topic_id
				AND i.user_id = ' . phpbb::$user->data['user_id'] . '
				AND ' . phpbb::$db->sql_in_set('t.forum_id', $forbidden_forum_ary, true, true),
		);
		$sql = phpbb::$db->sql_build_query('SELECT', $sql_array);
		$result = phpbb::$db->sql_query($sql);
		$topics_count = (int) phpbb::$db->sql_fetchfield('topics_count');
		phpbb::$db->sql_freeresult($result);

		if ($topics_count)
		{
			phpbb::$template->assign_vars(array(
				'PAGINATION'	=> generate_pagination($this->u_action, $topics_count, phpbb::$config['topics_per_page'], $start),
				'PAGE_NUMBER'	=> on_page($topics_count, phpbb::$config['topics_per_page'], $start),
				'TOTAL_TOPICS'	=> ($topics_count == 1) ? phpbb::$user->lang['VIEW_FORUM_TOPIC'] : sprintf(phpbb::$user->lang['VIEW_FORUM_TOPICS'], $topics_count))
			);
		}

		if ($mode == 'subscribed')
		{
			$sql_array = array(
				'SELECT'	=> 't.*, f.forum_name',

				'FROM'		=> array(
					TOPICS_WATCH_TABLE	=> 'tw',
					TOPICS_TABLE		=> 't'
				),

				'WHERE'		=> 'tw.user_id = ' . phpbb::$user->data['user_id'] . '
					AND t.topic_id = tw.topic_id
					AND ' . phpbb::$db->sql_in_set('t.forum_id', $forbidden_forum_ary, true, true),


				'ORDER_BY'	=> 't.topic_last_post_time DESC'
			);

			$sql_array['LEFT_JOIN'] = array();
		}
		else
		{
			$sql_array = array(
				'SELECT'	=> 't.*, f.forum_name, b.topic_id as b_topic_id',

				'FROM'		=> array(
					BOOKMARKS_TABLE		=> 'b',
				),

				'WHERE'		=> 'b.user_id = ' . phpbb::$user->data['user_id'] . '
					AND ' . phpbb::$db->sql_in_set('f.forum_id', $forbidden_forum_ary, true, true),

				'ORDER_BY'	=> 't.topic_last_post_time DESC'
			);

			$sql_array['LEFT_JOIN'] = array();
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_TABLE => 't'), 'ON' => 'b.topic_id = t.topic_id');
		}

		$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TABLE => 'f'), 'ON' => 't.forum_id = f.forum_id');

		if (phpbb::$config['load_db_lastread'])
		{
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TRACK_TABLE => 'ft'), 'ON' => 'ft.forum_id = t.forum_id AND ft.user_id = ' . phpbb::$user->data['user_id']);
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_TRACK_TABLE => 'tt'), 'ON' => 'tt.topic_id = t.topic_id AND tt.user_id = ' . phpbb::$user->data['user_id']);
			$sql_array['SELECT'] .= ', tt.mark_time, ft.mark_time AS forum_mark_time';
		}

		if (phpbb::$config['load_db_track'])
		{
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_POSTED_TABLE => 'tp'), 'ON' => 'tp.topic_id = t.topic_id AND tp.user_id = ' . phpbb::$user->data['user_id']);
			$sql_array['SELECT'] .= ', tp.topic_posted';
		}

		$sql = phpbb::$db->sql_build_query('SELECT', $sql_array);
		$result = phpbb::$db->sql_query_limit($sql, phpbb::$config['topics_per_page'], $start);

		$topic_list = $topic_forum_list = $global_announce_list = $rowset = array();
		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$topic_id = (isset($row['b_topic_id'])) ? $row['b_topic_id'] : $row['topic_id'];

			$topic_list[] = $topic_id;
			$rowset[$topic_id] = $row;

			$topic_forum_list[$row['forum_id']]['forum_mark_time'] = (phpbb::$config['load_db_lastread']) ? $row['forum_mark_time'] : 0;
			$topic_forum_list[$row['forum_id']]['topics'][] = $topic_id;

			if ($row['topic_type'] == POST_GLOBAL)
			{
				$global_announce_list[] = $topic_id;
			}
		}
		phpbb::$db->sql_freeresult($result);

		$topic_tracking_info = array();
		if (phpbb::$config['load_db_lastread'])
		{
			foreach ($topic_forum_list as $f_id => $topic_row)
			{
				$topic_tracking_info += get_topic_tracking($f_id, $topic_row['topics'], $rowset, array($f_id => $topic_row['forum_mark_time']), ($f_id == 0) ? $global_announce_list : false);
			}
		}
		else
		{
			foreach ($topic_forum_list as $f_id => $topic_row)
			{
				$topic_tracking_info += get_complete_topic_tracking($f_id, $topic_row['topics'], $global_announce_list);
			}
		}

		foreach ($topic_list as $topic_id)
		{
			$row = &$rowset[$topic_id];

			$forum_id = $row['forum_id'];
			$topic_id = (isset($row['b_topic_id'])) ? $row['b_topic_id'] : $row['topic_id'];

			$unread_topic = (isset($topic_tracking_info[$topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;

			// Replies
			$replies = (phpbb::$auth->acl_get('m_approve', $forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];

			if ($row['topic_status'] == ITEM_MOVED && !empty($row['topic_moved_id']))
			{
				$topic_id = $row['topic_moved_id'];
			}

			// Get folder img, topic status/type related information
			$folder_img = $folder_alt = $topic_type = '';
			topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);

			$view_topic_url_params = "f=$forum_id&amp;t=$topic_id";
			$view_topic_url = append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, $view_topic_url_params);

			// Send vars to template
			phpbb::$template->assign_block_vars('topicrow', array(
				'FORUM_ID'					=> $forum_id,
				'TOPIC_ID'					=> $topic_id,
				'FIRST_POST_TIME'			=> phpbb::$user->format_date($row['topic_time']),
				'LAST_POST_SUBJECT'			=> $row['topic_last_post_subject'],
				'LAST_POST_TIME'			=> phpbb::$user->format_date($row['topic_last_post_time']),
				'LAST_VIEW_TIME'			=> phpbb::$user->format_date($row['topic_last_view_time']),

				'TOPIC_AUTHOR'				=> get_username_string('username', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'TOPIC_AUTHOR_COLOUR'		=> get_username_string('colour', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'TOPIC_AUTHOR_FULL'			=> get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'U_TOPIC_AUTHOR'			=> get_username_string('profile', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),

				'LAST_POST_AUTHOR'			=> get_username_string('username', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'LAST_POST_AUTHOR_COLOUR'	=> get_username_string('colour', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'LAST_POST_AUTHOR_FULL'		=> get_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'U_LAST_POST_AUTHOR'		=> get_username_string('profile', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),

				'S_DELETED_TOPIC'	=> (!$row['topic_id']) ? true : false,
				'S_GLOBAL_TOPIC'	=> (!$forum_id) ? true : false,

				'PAGINATION'		=> topic_generate_pagination($replies, append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, 'f=' . (($row['forum_id']) ? $row['forum_id'] : $forum_id) . "&amp;t=$topic_id")),
				'REPLIES'			=> $replies,
				'VIEWS'				=> $row['topic_views'],
				'TOPIC_TITLE'		=> censor_text($row['topic_title']),
				'TOPIC_TYPE'		=> $topic_type,
				'FORUM_NAME'		=> $row['forum_name'],

				'TOPIC_FOLDER_IMG'		=> phpbb::$user->img($folder_img, $folder_alt),
				'TOPIC_FOLDER_IMG_SRC'	=> phpbb::$user->img($folder_img, $folder_alt, false, '', 'src'),
				'TOPIC_FOLDER_IMG_ALT'	=> phpbb::$user->lang[$folder_alt],
				'TOPIC_ICON_IMG'		=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['img'] : '',
				'TOPIC_ICON_IMG_WIDTH'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['width'] : '',
				'TOPIC_ICON_IMG_HEIGHT'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['height'] : '',
				'ATTACH_ICON_IMG'		=> (phpbb::$auth->acl_get('u_download') && phpbb::$auth->acl_get('f_download', $forum_id) && $row['topic_attachment']) ? phpbb::$user->img('icon_topic_attach', phpbb::$user->lang['TOTAL_ATTACHMENTS']) : '',

				'S_TOPIC_TYPE'			=> $row['topic_type'],
				'S_USER_POSTED'			=> (!empty($row['topic_posted'])) ? true : false,
				'S_UNREAD_TOPIC'		=> $unread_topic,

				'U_NEWEST_POST'			=> append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, $view_topic_url_params . '&amp;view=unread') . '#unread',
				'U_LAST_POST'			=> append_sid(phpbb::$phpbb_root_path . "viewtopic." . phpbb::$phpEx, $view_topic_url_params . '&amp;p=' . $row['topic_last_post_id']) . '#p' . $row['topic_last_post_id'],
				'U_VIEW_TOPIC'			=> $view_topic_url,
				'U_VIEW_FORUM'			=> append_sid(phpbb::$phpbb_root_path . "viewforum." . phpbb::$phpEx, 'f=' . $forum_id),
			));
		}
	}
}

?>