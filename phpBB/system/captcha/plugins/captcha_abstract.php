<?php
/**
*
* @package VC
* @version $Id$
* @copyright (c) 2006, 2008 phpBB Group
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
* This class holds the code shared by the two default 3.0.x CAPTCHAs.
*
* @package VC
*/
class phpbb_default_captcha
{
	var $confirm_id;
	var $confirm_code;
	var $code;
	var $seed;
	var $attempts = 0;
	var $type;
	var $solved = 0;
	var $captcha_vars = false;

	function init($type)
	{
		// read input
		$this->confirm_id = request_var('confirm_id', '');
		$this->confirm_code = request_var('confirm_code', '');
		$refresh = request_var('refresh_vc', false) && phpbb::$config['confirm_refresh'];

		$this->type = (int) $type;

		if (!strlen($this->confirm_id) || !$this->load_code())
		{
			// we have no confirm ID, better get ready to display something
			$this->generate_code();
		}
		else if ($refresh)
		{
			$this->regenerate_code();
		}
	}

	function execute_demo()
	{

		$this->code = gen_rand_string_friendly(mt_rand(CAPTCHA_MIN_CHARS, CAPTCHA_MAX_CHARS));
		$this->seed = hexdec(substr(unique_id(), 4, 10));

		// compute $seed % 0x7fffffff
		$this->seed -= 0x7fffffff * floor($this->seed / 0x7fffffff);

		$captcha = new captcha();
		define('IMAGE_OUTPUT', 1);
		$captcha->execute($this->code, $this->seed);
	}

	function execute()
	{
		if (empty($this->code))
		{
			if (!$this->load_code())
			{
				// invalid request, bail out
				return false;
			}
		}
		$captcha = new captcha();
		define('IMAGE_OUTPUT', 1);
		$captcha->execute($this->code, $this->seed);
	}

	function get_template()
	{

		if ($this->is_solved())
		{
			return false;
		}
		else
		{
			$link = append_sid(phpbb::$phpbb_root_path . 'ucp.' . $phpEx,  'mode=confirm&amp;confirm_id=' . $this->confirm_id . '&amp;type=' . $this->type);
			$explain = phpbb::$user->lang(($this->type != CONFIRM_POST) ? 'CONFIRM_EXPLAIN' : 'POST_CONFIRM_EXPLAIN', '<a href="mailto:' . htmlspecialchars(phpbb::$config['board_contact']) . '">', '</a>');

			phpbb::$template->assign_vars(array(
				'CONFIRM_IMAGE_LINK'		=> $link,
				'CONFIRM_IMAGE'				=> '<img src="' . $link . '" />',
				'CONFIRM_IMG'				=> '<img src="' . $link . '" />',
				'CONFIRM_ID'				=> $this->confirm_id,
				'S_CONFIRM_CODE'			=> true,
				'S_TYPE'					=> $this->type,
				'S_CONFIRM_REFRESH'			=> (phpbb::$config['enable_confirm'] && phpbb::$config['confirm_refresh'] && $this->type == CONFIRM_REG) ? true : false,
				'L_CONFIRM_EXPLAIN'			=> $explain,
			));

			return 'captcha_default.html';
		}
	}

	function get_demo_template($id)
	{

		$variables = '';

		if (is_array($this->captcha_vars))
		{
			foreach ($this->captcha_vars as $captcha_var => $template_var)
			{
				$variables .= '&amp;' . rawurlencode($captcha_var) . '=' . request_var($captcha_var, (int) phpbb::$config[$captcha_var]);
			}
		}

		// acp_captcha has a delivery function; let's use it
		phpbb::$template->assign_vars(array(
			'CONFIRM_IMAGE'		=> append_sid($phpbb_admin_path . 'index.' . $phpEx, 'captcha_demo=1&amp;mode=visual&amp;i=' . $id . '&amp;select_captcha=' . $this->get_class_name()) . $variables,
			'CONFIRM_ID'		=> $this->confirm_id,
		));

		return 'captcha_default_acp_demo.html';
	}

	function get_hidden_fields()
	{
		$hidden_fields = array();

		// this is required for posting.php - otherwise we would forget about the captcha being already solved
		if ($this->solved)
		{
			$hidden_fields['confirm_code'] = $this->confirm_code;
		}
		$hidden_fields['confirm_id'] = $this->confirm_id;
		return $hidden_fields;
	}

	function garbage_collect($type)
	{
		$sql = 'SELECT DISTINCT c.session_id
			FROM ' . CONFIRM_TABLE . ' c
			LEFT JOIN ' . SESSIONS_TABLE . ' s ON (c.session_id = s.session_id)
			WHERE s.session_id IS NULL' .
				((empty($type)) ? '' : ' AND c.confirm_type = ' . (int) $type);
		$result = phpbb::$db->sql_query($sql);

		if ($row = phpbb::$db->sql_fetchrow($result))
		{
			$sql_in = array();
			do
			{
				$sql_in[] = (string) $row['session_id'];
			}
			while ($row = phpbb::$db->sql_fetchrow($result));

			if (sizeof($sql_in))
			{
				$sql = 'DELETE FROM ' . CONFIRM_TABLE . '
					WHERE ' . phpbb::$db->sql_in_set('session_id', $sql_in);
				phpbb::$db->sql_query($sql);
			}
		}
		phpbb::$db->sql_freeresult($result);
	}

	function uninstall()
	{
		$this->garbage_collect(0);
	}

	function install()
	{
		return;
	}

	function validate()
	{

		if (empty(phpbb::$user->lang))
		{
			phpbb::$user->setup();
		}

		$error = '';
		if (!$this->confirm_id)
		{
			$error = phpbb::$user->lang['CONFIRM_CODE_WRONG'];
		}
		else
		{
			if ($this->check_code())
			{
				// $this->delete_code(); commented out to allow posting.php to repeat the question
				$this->solved = true;
			}
			else
			{
				$error = phpbb::$user->lang['CONFIRM_CODE_WRONG'];
			}
		}

		if (strlen($error))
		{
			// okay, incorrect answer. Let's ask a new question.
			$this->new_attempt();
			return $error;
		}
		else
		{
			return false;
		}
	}

	/**
	* The old way to generate code, suitable for GD and non-GD. Resets the internal state.
	*/
	function generate_code()
	{

		$this->code = gen_rand_string_friendly(mt_rand(CAPTCHA_MIN_CHARS, CAPTCHA_MAX_CHARS));
		$this->confirm_id = md5(unique_id(phpbb::$user->ip));
		$this->seed = hexdec(substr(unique_id(), 4, 10));
		$this->solved = 0;
		// compute $seed % 0x7fffffff
		$this->seed -= 0x7fffffff * floor($this->seed / 0x7fffffff);

		$sql = 'INSERT INTO ' . CONFIRM_TABLE . ' ' . phpbb::$db->sql_build_array('INSERT', array(
				'confirm_id'	=> (string) $this->confirm_id,
				'session_id'	=> (string) phpbb::$user->session_id,
				'confirm_type'	=> (int) $this->type,
				'code'			=> (string) $this->code,
				'seed'			=> (int) $this->seed)
		);
		phpbb::$db->sql_query($sql);
	}

	/**
	* New Question, if desired.
	*/
	function regenerate_code()
	{
		$this->code = gen_rand_string_friendly(mt_rand(CAPTCHA_MIN_CHARS, CAPTCHA_MAX_CHARS));
		$this->seed = hexdec(substr(unique_id(), 4, 10));
		$this->solved = 0;
		// compute $seed % 0x7fffffff
		$this->seed -= 0x7fffffff * floor($this->seed / 0x7fffffff);

		$sql = 'UPDATE ' . CONFIRM_TABLE . ' SET ' . phpbb::$db->sql_build_array('UPDATE', array(
				'code'			=> (string) $this->code,
				'seed'			=> (int) $this->seed)) . '
				WHERE
				confirm_id = \'' . phpbb::$db->sql_escape($this->confirm_id) . '\'
					AND session_id = \'' . phpbb::$db->sql_escape(phpbb::$user->session_id) . '\'';
		phpbb::$db->sql_query($sql);
	}

	/**
	* New Question, if desired.
	*/
	function new_attempt()
	{

		$this->code = gen_rand_string_friendly(mt_rand(CAPTCHA_MIN_CHARS, CAPTCHA_MAX_CHARS));
		$this->seed = hexdec(substr(unique_id(), 4, 10));
		$this->solved = 0;
		// compute $seed % 0x7fffffff
		$this->seed -= 0x7fffffff * floor($this->seed / 0x7fffffff);

		$sql = 'UPDATE ' . CONFIRM_TABLE . ' SET ' . phpbb::$db->sql_build_array('UPDATE', array(
				'code'			=> (string) $this->code,
				'seed'			=> (int) $this->seed)) . '
				, attempts = attempts + 1
				WHERE
				confirm_id = \'' . phpbb::$db->sql_escape($this->confirm_id) . '\'
					AND session_id = \'' . phpbb::$db->sql_escape(phpbb::$user->session_id) . '\'';
		phpbb::$db->sql_query($sql);
	}

	/**
	* Look up everything we need for painting&checking.
	*/
	function load_code()
	{

		$sql = 'SELECT code, seed, attempts
			FROM ' . CONFIRM_TABLE . "
			WHERE confirm_id = '" . phpbb::$db->sql_escape($this->confirm_id) . "'
				AND session_id = '" . phpbb::$db->sql_escape(phpbb::$user->session_id) . "'
				AND confirm_type = " . $this->type;
		$result = phpbb::$db->sql_query($sql);
		$row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		if ($row)
		{
			$this->code = $row['code'];
			$this->seed = $row['seed'];
			$this->attempts = $row['attempts'];
			return true;
		}

		return false;
	}

	function check_code()
	{
		return (strcasecmp($this->code, $this->confirm_code) === 0);
	}

	function delete_code()
	{
		$sql = 'DELETE FROM ' . CONFIRM_TABLE . "
			WHERE confirm_id = '" . phpbb::$db->sql_escape($confirm_id) . "'
				AND session_id = '" . phpbb::$db->sql_escape(phpbb::$user->session_id) . "'
				AND confirm_type = " . $this->type;
		phpbb::$db->sql_query($sql);
	}

	function get_attempt_count()
	{
		return $this->attempts;
	}

	function reset()
	{
		$sql = 'DELETE FROM ' . CONFIRM_TABLE . "
			WHERE session_id = '" . phpbb::$db->sql_escape(phpbb::$user->session_id) . "'
				AND confirm_type = " . (int) $this->type;
		phpbb::$db->sql_query($sql);

		// we leave the class usable by generating a new question
		$this->generate_code();
	}

	function is_solved()
	{
		if (request_var('confirm_code', false) && $this->solved === 0)
		{
			$this->validate();
		}
		return (bool) $this->solved;
	}

	/**
	*  API function
	*/
	function has_config()
	{
		return false;
	}

}

?>