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

// Start session management
phpbb::$user->session_begin();
phpbb::$auth->acl(phpbb::$user->data);
phpbb::$user->setup();

$mode = request_var('mode', '');

// Load the appropriate faq file
switch ($mode)
{
	case 'bbcode':
		$l_title = phpbb::$user->lang['BBCODE_GUIDE'];
		phpbb::$user->add_lang('bbcode', false, true);
	break;

	default:
		$l_title = phpbb::$user->lang['FAQ_EXPLAIN'];
		phpbb::$user->add_lang('faq', false, true);
	break;
}

// Pull the array data from the lang pack
$switch_column = $found_switch = false;
$help_blocks = array();
foreach (phpbb::$user->help as $help_ary)
{
	if ($help_ary[0] == '--')
	{
		if ($help_ary[1] == '--')
		{
			$switch_column = true;
			$found_switch = true;
			continue;
		}

		phpbb::$template->assign_block_vars('faq_block', array(
			'BLOCK_TITLE'		=> $help_ary[1],
			'SWITCH_COLUMN'		=> $switch_column,
		));

		if ($switch_column)
		{
			$switch_column = false;
		}
		continue;
	}

	phpbb::$template->assign_block_vars('faq_block.faq_row', array(
		'FAQ_QUESTION'		=> $help_ary[0],
		'FAQ_ANSWER'		=> $help_ary[1])
	);
}

// Lets build a page ...
phpbb::$template->assign_vars(array(
	'L_FAQ_TITLE'				=> $l_title,
	'L_BACK_TO_TOP'				=> phpbb::$user->lang['BACK_TO_TOP'],

	'SWITCH_COLUMN_MANUALLY'	=> (!$found_switch) ? true : false,
));

page_header($l_title, false);

phpbb::$template->set_filenames(array(
	'body' => 'faq_body.html')
);
make_jumpbox(append_sid(phpbb::$phpbb_root_path . "viewforum.$phpEx"));

page_footer();

?>