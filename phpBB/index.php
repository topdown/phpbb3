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
 * This is the installer for the phpBB3 / aMember Membership Package
 *
 *
 * PHP version 5
 *
 * LICENSE: This is PRIVATE source code developed for clients.
 * It is in no way transferable and copy rights belong to Jeff Behnke @ Valid-Webs.com
 *
 * Created 8/22/11, 8:32 PM
 *
 * @category   phpBB3
 * @package    Package - jeff
 * @author     Jeff Behnke a.k.a topdown <code@valid-webs.com>
 * @copyright  (c) 2011 Valid-Webs.com
 * @license    VW Pro
 * @version    1.0.0
 */



/**
* @ignore
*/
define('IN_PHPBB', true);

//@todo this statement makes no sense as PHPBB_ROOT_PATH can not be defined yet
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'system/common.' . $phpEx);
include(phpbb::$phpbb_root_path . 'system/includes/functions_display.' . phpbb::$phpEx);

// Start session management
phpbb::$user->session_begin();
phpbb::$auth->acl(phpbb::$user->data);
phpbb::$user->setup('viewforum');

display_forums('', phpbb::$config['load_moderators']);

// Set some stats, get posts count from forums data if we... hum... retrieve all forums data
$total_posts	= phpbb::$config['num_posts'];
$total_topics	= phpbb::$config['num_topics'];
$total_users	= phpbb::$config['num_users'];

$l_total_user_s = ($total_users == 0) ? 'TOTAL_USERS_ZERO' : 'TOTAL_USERS_OTHER';
$l_total_post_s = ($total_posts == 0) ? 'TOTAL_POSTS_ZERO' : 'TOTAL_POSTS_OTHER';
$l_total_topic_s = ($total_topics == 0) ? 'TOTAL_TOPICS_ZERO' : 'TOTAL_TOPICS_OTHER';

// Grab group details for legend display
if (phpbb::$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel'))
{
	$sql = 'SELECT group_id, group_name, group_colour, group_type
		FROM ' . GROUPS_TABLE . '
		WHERE group_legend = 1
		ORDER BY group_name ASC';
}
else
{
	$sql = 'SELECT g.group_id, g.group_name, g.group_colour, g.group_type
		FROM ' . GROUPS_TABLE . ' g
		LEFT JOIN ' . USER_GROUP_TABLE . ' ug
			ON (
				g.group_id = ug.group_id
				AND ug.user_id = ' . phpbb::$user->data['user_id'] . '
				AND ug.user_pending = 0
			)
		WHERE g.group_legend = 1
			AND (g.group_type <> ' . GROUP_HIDDEN . ' OR ug.user_id = ' . phpbb::$user->data['user_id'] . ')
		ORDER BY g.group_name ASC';
}
$result = phpbb::$db->sql_query($sql);

$legend = array();
while ($row = phpbb::$db->sql_fetchrow($result))
{
	$colour_text = ($row['group_colour']) ? ' style="color:#' . $row['group_colour'] . '"' : '';
	$group_name = ($row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $row['group_name']] : $row['group_name'];

	if ($row['group_name'] == 'BOTS' || (phpbb::$user->data['user_id'] != ANONYMOUS && !phpbb::$auth->acl_get('u_viewprofile')))
	{
		$legend[] = '<span' . $colour_text . '>' . $group_name . '</span>';
	}
	else
	{
		$legend[] = '<a' . $colour_text . ' href="' . append_sid(phpbb::$phpbb_root_path . "memberlist." . phpbb::$phpEx, 'mode=group&amp;g=' . $row['group_id']) . '">' . $group_name . '</a>';
	}
}
phpbb::$db->sql_freeresult($result);

$legend = implode(', ', $legend);

// Generate birthday list if required ...
$birthday_list = '';
if (phpbb::$config['load_birthdays'] && phpbb::$config['allow_birthdays'])
{
	$now = getdate(time() + phpbb::$user->timezone + phpbb::$user->dst - date('Z'));
	$sql = 'SELECT u.user_id, u.username, u.user_colour, u.user_birthday
		FROM ' . USERS_TABLE . ' u
		LEFT JOIN ' . BANLIST_TABLE . " b ON (u.user_id = b.ban_userid)
		WHERE (b.ban_id IS NULL
			OR b.ban_exclude = 1)
			AND u.user_birthday LIKE '" . phpbb::$db->sql_escape(sprintf('%2d-%2d-', $now['mday'], $now['mon'])) . "%'
			AND u.user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
	$result = phpbb::$db->sql_query($sql);

	while ($row = phpbb::$db->sql_fetchrow($result))
	{
		$birthday_list .= (($birthday_list != '') ? ', ' : '') . get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);

		if ($age = (int) substr($row['user_birthday'], -4))
		{
			$birthday_list .= ' (' . ($now['year'] - $age) . ')';
		}
	}
	phpbb::$db->sql_freeresult($result);
}

// Assign index specific vars
phpbb::$template->assign_vars(array(
	'TOTAL_POSTS'	=> sprintf(phpbb::$user->lang[$l_total_post_s], $total_posts),
	'TOTAL_TOPICS'	=> sprintf(phpbb::$user->lang[$l_total_topic_s], $total_topics),
	'TOTAL_USERS'	=> sprintf(phpbb::$user->lang[$l_total_user_s], $total_users),
	'NEWEST_USER'	=> sprintf(phpbb::$user->lang['NEWEST_USER'], get_username_string('full', phpbb::$config['newest_user_id'], phpbb::$config['newest_username'], phpbb::$config['newest_user_colour'])),

	'LEGEND'		=> $legend,
	'BIRTHDAY_LIST'	=> $birthday_list,

	'FORUM_IMG'				=> phpbb::$user->img('forum_read', 'NO_UNREAD_POSTS'),
	'FORUM_UNREAD_IMG'			=> phpbb::$user->img('forum_unread', 'UNREAD_POSTS'),
	'FORUM_LOCKED_IMG'		=> phpbb::$user->img('forum_read_locked', 'NO_UNREAD_POSTS_LOCKED'),
	'FORUM_UNREAD_LOCKED_IMG'	=> phpbb::$user->img('forum_unread_locked', 'UNREAD_POSTS_LOCKED'),

	'S_LOGIN_ACTION'			=> append_sid(phpbb::$phpbb_root_path . "ucp." . phpbb::$phpEx, 'mode=login'),
	'S_DISPLAY_BIRTHDAY_LIST'	=> (phpbb::$config['load_birthdays']) ? true : false,

	'U_MARK_FORUMS'		=> (phpbb::$user->data['is_registered'] || phpbb::$config['load_anon_lastread']) ? append_sid(phpbb::$phpbb_root_path . "index." . phpbb::$phpEx, 'hash=' . generate_link_hash('global') . '&amp;mark=forums') : '',
	'U_MCP'				=> (phpbb::$auth->acl_get('m_') || phpbb::$auth->acl_getf_global('m_')) ? append_sid(phpbb::$phpbb_root_path . "mcp." . phpbb::$phpEx, 'i=main&amp;mode=front', true, phpbb::$user->session_id) : '')
);

// Output page
page_header(phpbb::$user->lang['INDEX']);

phpbb::$template->set_filenames(array(
	'body' => 'index_body.html')
);

page_footer();

?>