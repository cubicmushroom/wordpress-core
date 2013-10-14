<?php
/**
 * Exception thrown when custom post type registration fails
 *
 * PHP version 5
 * 
 * @category   WordPress_Plugins
 * @package    CubicMushroom_WP
 * @subpackage Core
 * @author     Toby Griffiths <toby@cubicmushroom.co.uk>
 * @license    http://opensource.org/licenses/MIT MIT
 * @link       http://cubicmushroom.co.uk
 **/

/**
 * Exception thrown when custom post type registration fails
 *
 * PHP version 5
 * 
 * @category   WordPress_Plugins
 * @package    CubicMushroom_WP
 * @subpackage Core
 * @author     Toby Griffiths <toby@cubicmushroom.co.uk>
 * @license    http://opensource.org/licenses/MIT MIT
 * @link       http://cubicmushroom.co.uk
 **/
class CubicMushroom_WP_Core_Exception_PostTypeRegistrationFailedException extends Exception
{
    /**
     * The args used when attempting to create post type
     * @var array
     */
    protected $args;


    /**
     * Prepares error message based on post type being registered & the error
     * returned
     * 
     * @param string   $postType Name of the post type class that was attempting to
     *                           register itself
     * @param array    $args     Argument used to attempt to register post type
     * @param WP_Error $error    Error returned when attempting to register post
     *                            type
     *
     * @return void
     */
    public function __construct($postType, $args, WP_Error $error)
    {
        // Store args
        $this->args = $args;

        // Prepare message
        $msg = sprintf(
            "Unable to register '%s' custom post type.  WP_Error: '%s'",
            $postType,
            $error->get_error_message()
        );

        // Call Exception::__construct()
        parent::__construct($msg);
    }
}