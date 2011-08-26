<?php


/**
 * Class content_hook
 *
 * PHP version 5
 *
 * 8/23/11, 12:59 AM
 *
 * @category  PhpBB
 * @package   PhpBB Core
 * @subpackage Hooks
 * @author    phpBB Group <username@example.com>
 * @author    Modified by Jeff Behnke <code@valid-webs.com>
 * @copyright 2005 (c) phpBB Group
 * @license   http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version   3.0.9
 * @link      http://phpbb.com
 */

class content_hook
{
        /**
         * @var array $data
         */
        public $data = array();
     
        /**
         * @var array $content
         */
        public $content = array();
     
        /**
         * @var string $place
         */
        public $place;
     
        function __construct()
        {
                return;
        }

	/**
	 * @param $data
	 * @param string $place The position to place the $data
	 * @internal param array $object $data  The content to add
	 * @return string $content
	 */
        function filter($data, $place = 'before')
        {
                //The spot to add the new content
                $this->place = $place;
     
                //Keep this array usable throughout the class
                $this->data = (object)$data;
     
                switch($this->place)
                {
                        //Add the data array before everything else
                        case 'before':
                                $this->content = array(
                                        $this->data->add,
                                        $this->content()->post_title,
                                        $this->content()->content,
                                        $this->content()->post_footer
                                );
                        break;
     
                        case 'post_title':
                                $this->content = array(
                                        $this->content()->post_title . $this->data->add,
                                        $this->content()->content,
                                        $this->content()->post_footer
                                );
                        break;
     
                        case 'content' :
                                $this->content = array(
                                        $this->content()->post_title,
                                        $this->content()->content . $this->data->add,
                                        $this->content()->post_footer
                                );
                        break;
     
                        case 'post_footer':
                                $this->content = array(
                                        $this->content()->post_title,
                                        $this->content()->content,
                                        $this->content()->post_footer . $this->data->add
                                );
                        break;
                }
     
                return implode($this->content, '');
        }

	/**
	 * @property post_title
	 * @param $content
	 * @return object
	 */
        function content($content)
        {
                /*$post = (object) array(
                        'post_title'    => 'Post title<br />',
                        'content'               => 'Some post content<br />',
                        'post_footer'   => 'Post signature<br />',
                );*/
                return $this->content = $content;
        }
}
