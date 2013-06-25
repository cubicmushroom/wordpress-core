<?php
/**
 * Base PotType class to be extended to create custom post types
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

namespace CubicMushroom\WP\Core;

use CubicMushroom\WP\Exception\PostTypeRegistrationFailedException;
use CubicMushroom\WP\Plugins\Plugin;

/**
 * NCF Fund & Application WordPress plugin core class
 * 
 * @category   WordPress_Plugins
 * @package    CubicMushroom_WP
 * @subpackage Core
 * @author     Toby Griffiths <toby@cubicmushroom.co.uk>
 * @license    http://opensource.org/licenses/MIT MIT
 * @link       http://cubicmushroom.co.uk
 **/
abstract class PostType
{
    /**
     * Stores the instance of the plugin class to prevent duplicate objects being
     * created
     * @var PostType
     */
    static protected $self;

    /**
     * Arguments used when registering the post type.
     * 
     * Values set in CubicMushroom\WP\Core\PostType class are defaults that can be
     * overridden in descendant classes.
     * 
     * @var array
     */
    static $postArgs = array(
        'public' => true,
        // 'publicly_queryable' => true,
        // 'show_ui' => true,
        // 'show_in_menu' => true,
        // 'query_var' => true,
        // // 'rewrite' => array(),
        // 'has_archive' => true,
        // 'hierarchical' => false,
        // 'menu_position' => null,
        // 'supports' => array(
        //     'title',
        //     'editor',
        //     'author',
        //     'thumbnail',
        //     'excerpt',
        //     'comments',
        //     'revisions',
        //     'trackbacks',
        //     'page-attributes',
        //     'custom-fields'
        // ),
        // 'taxonomies' => array(),
        // 'capability_type' => 'post',
        // 'capabilities' => array(),
        'label' => 'Label not set',
    );

    /**
     * Labels for post type
     * @var array
     */
    protected $labels;

    /**
     * registers the custom post type
     *
     * @param Plugin $plugin Plugin object that is responsible for this custo post
     *                       type
     *
     * @throws PostTypeRegistrationFailedException If error returned when attempting
     *                                             to register post type
     * 
     * @return PostType
     */
    static public function register(Plugin $plugin)
    {
        $class = get_called_class();
        $args = wp_parse_args($class::$postArgs, self::$postArgs);
        $postType = register_post_type($class::POST_SLUG, $args);
        if ($postType instanceof \WP_Error) {
            throw new PostTypeRegistrationFailedException($class, $args, $postType);
        }
        return $postType;
    }
} // END class PostType