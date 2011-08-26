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
*/
define('IN_PHPBB', true);
define('IN_CRON', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'system/common.' . $phpEx);

// Do not update users last page entry
phpbb::$user->session_begin(false);
phpbb::$auth->acl(phpbb::$user->data);

$cron_type = request_var('cron_type', '');

// Output transparent gif
header('Cache-Control: no-cache');
header('Content-type: image/gif');
header('Content-length: 43');

echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

// Flush here to prevent browser from showing the page as loading while running cron.
flush();

if (!isset(phpbb::$config['cron_lock']))
{
	set_config('cron_lock', '0', true);
}

// make sure cron doesn't run multiple times in parallel
if (phpbb::$config['cron_lock'])
{
	// if the other process is running more than an hour already we have to assume it
	// aborted without cleaning the lock
	$time = explode(' ', phpbb::$config['cron_lock']);
	$time = $time[0];

	if ($time + 3600 >= time())
	{
		exit;
	}
}

define('CRON_ID', time() . ' ' . unique_id());

$sql = 'UPDATE ' . CONFIG_TABLE . "
	SET config_value = '" . phpbb::$db->sql_escape(CRON_ID) . "'
	WHERE config_name = 'cron_lock' AND config_value = '" . phpbb::$db->sql_escape(phpbb::$config['cron_lock']) . "'";
phpbb::$db->sql_query($sql);

// another cron process altered the table between script start and UPDATE query so exit
if (phpbb::$db->sql_affectedrows() != 1)
{
	exit;
}

/**
* Run cron-like action
* Real cron-based layer will be introduced in 3.2
*/
switch ($cron_type)
{
	case 'queue':

		if (time() - phpbb::$config['queue_interval'] <= phpbb::$config['last_queue_run'] || !file_exists(phpbb::$phpbb_root_path . 'cache/queue.' . phpbb::$phpEx))
		{
			break;
		}

		include_once(phpbb::$phpbb_root_path . 'system/core/messenger.' . phpbb::$phpEx);
		$queue = new queue();

		$queue->process();

	break;

	case 'tidy_cache':

		if (time() - phpbb::$config['cache_gc'] <= phpbb::$config['cache_last_gc'] || !method_exists(phpbb::$cache, 'tidy'))
		{
			break;
		}

		phpbb::$cache->tidy();

	break;

	case 'tidy_search':
		
		// Select the search method
		$search_type = basename(phpbb::$config['search_type']);

		if (time() - phpbb::$config['search_gc'] <= phpbb::$config['search_last_gc'] || !file_exists(phpbb::$phpbb_root_path . 'system/search/' . $search_type . '.' . phpbb::$phpEx))
		{
			break;
		}

		include_once(phpbb::$phpbb_root_path . "system/search/$search_type." . phpbb::$phpEx);

		// We do some additional checks in the module to ensure it can actually be utilised
		$error = false;
		$search = new $search_type($error);

		if ($error)
		{
			break;
		}

		$search->tidy();

	break;

	case 'tidy_warnings':

		if (time() - phpbb::$config['warnings_gc'] <= phpbb::$config['warnings_last_gc'])
		{
			break;
		}

		include_once(phpbb::$phpbb_root_path . 'system/includes/functions_admin.' . phpbb::$phpEx);

		tidy_warnings();

	break;

	case 'tidy_database':

		if (time() - phpbb::$config['database_gc'] <= phpbb::$config['database_last_gc'])
		{
			break;
		}

		include_once(phpbb::$phpbb_root_path . 'system/includes/functions_admin.' . phpbb::$phpEx);

		tidy_database();

	break;

	case 'tidy_sessions':

		if (time() - phpbb::$config['session_gc'] <= phpbb::$config['session_last_gc'])
		{
			break;
		}

		phpbb::$user->session_gc();

	break;

	case 'prune_forum':

		$forum_id = request_var('f', 0);

		$sql = 'SELECT forum_id, prune_next, enable_prune, prune_days, prune_viewed, forum_flags, prune_freq
			FROM ' . FORUMS_TABLE . "
			WHERE forum_id = $forum_id";
		$result = phpbb::$db->sql_query($sql);
		$row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		if (!$row)
		{
			break;
		}

		// Do the forum Prune thang
		if ($row['prune_next'] < time() && $row['enable_prune'])
		{
			include_once(phpbb::$phpbb_root_path . 'system/includes/functions_admin.' . phpbb::$phpEx);

			if ($row['prune_days'])
			{
				auto_prune($row['forum_id'], 'posted', $row['forum_flags'], $row['prune_days'], $row['prune_freq']);
			}

			if ($row['prune_viewed'])
			{
				auto_prune($row['forum_id'], 'viewed', $row['forum_flags'], $row['prune_viewed'], $row['prune_freq']);
			}
		}

	break;
}

// Unloading cache and closing db after having done the dirty work.
unlock_cron();
garbage_collection();

exit;


/**
* Unlock cron script
*/
function unlock_cron()
{

	$sql = 'UPDATE ' . CONFIG_TABLE . "
		SET config_value = '0'
		WHERE config_name = 'cron_lock' AND config_value = '" . phpbb::$db->sql_escape(CRON_ID) . "'";
	phpbb::$db->sql_query($sql);
}

?>