<?php

/**
 * Permission Auth system for phpBB3
 *
 * PHP version 5
 *
 * @category  PhpBB3
 * @package   PhpBB3/Core
 * @author    phpBB Group <username@example.com>
 * @author    Modified by Jeff Behnke <code@valid-webs.com>
 * @copyright 2005 (c) phpBB Group
 * @license   http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version   3.0.9
 * @link      http://phpbb.com
 */

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
 * Class phpbb_error_collector
 * 
 * @category  PhpBB3
 * @package   PhpBB3/Core
 * @author    phpBB Group <username@example.com>
 * @author    Modified by Jeff Behnke <code@valid-webs.com>
 * @copyright 2005 (c) phpBB Group
 * @license   http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version   3.0.9
 * @link      http://phpbb.com
 */
class phpbb_error_collector
{
	/**
	 * @var array
	 */
	protected $errors;

	/**
	 * Constructor
	 *
	 * @return array
	 */
	public function phpbb_error_collector()
	{
		return $this->errors = array();
	}

	/**
	 * Install this error handler
	 *
	 * @return mixed
	 */
	protected function install()
	{
		return set_error_handler(array(&$this, 'error_handler'));
	}

	/**
	 * Uninstall the handler and restore the default
	 * 
	 * @return bool
	 */
	protected function uninstall()
	{
		return restore_error_handler();
	}

	/**
	 * The new error handler
	 *
	 * @param mixed      $errno
	 * @param string     $msg_text
	 * @param string     $errfile
	 * @param int|string $errline
	 * @return mixed
	 */
	protected function error_handler($errno, $msg_text, $errfile, $errline)
	{
		$this->errors[] = array($errno, $msg_text, $errfile, $errline);
	}

	/**
	 * Format error output
	 * 
	 * @return string
	 */
	protected function format_errors()
	{
		$text = '';
		foreach ($this->errors as $error)
		{
			if (!empty($text))
			{
				$text .= "<br />\n";
			}
			list($errno, $msg_text, $errfile, $errline) = $error;
			$text .= "Errno $errno: $msg_text";
			if (defined('DEBUG_EXTRA') || defined('IN_INSTALL'))
			{
				$text .= " at $errfile line $errline";
			}
		}
		return $text;
	}
}
