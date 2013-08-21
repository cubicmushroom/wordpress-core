<?php
/**
 * Base PotType class to be extended to create custom post types
 *
 * PHP version 5
 * 
 * @category   WordPress
 * @package    CubicMushroom_WP
 * @subpackage Core
 * @author     Toby Griffiths <toby@cubicmushroom.co.uk>
 * @license    http://opensource.org/licenses/MIT MIT
 * @link       http://cubicmushroom.co.uk
 **/

namespace CubicMushroom\WP\Core;

use CubicMushroom\WP\Core\Exception\BadCallbackException;
use CubicMushroom\WP\Core\Exception\PostNotFoundException;
use CubicMushroom\WP\Core\Exception\PostTypeRegistrationFailedException;
use CubicMushroom\WP\Core\Base;


if (!class_exists('\CubicMushroom\WP\Core\PostType')) {
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
         * Registers the custom post type
         *
         * @param \CubicMushroom\WP\Core\Base $owner Plugin object that is
         *                                            responsible for this custom
         *                                            post type
         *
         * @throws PostTypeRegistrationFailedException If error returned when
         *                                             attempting to register post
         *                                             type
         * 
         * @return PostType
         */
        static public function register(Base $owner)
        {
            $class = get_called_class();
            $args = wp_parse_args($class::$postArgs, self::$postArgs);
            $postType = register_post_type($class::POST_SLUG, $args);
            if ($postType instanceof \WP_Error) {
                throw new PostTypeRegistrationFailedException($class, $args, $postType);
            }

            if (!empty($class::$metaboxes)) {
                add_action('add_meta_boxes', array($class, 'addMetaboxes'));
            }

            return $postType;
        }



        /**
         * Static constructor methods
         */

        /**
         * Creates a PostType object based on a 
         *
         * @throws InvalidArgumentException If no $postID provided
         * @throws PostNotFoundException    If unable to find the fund requested
         * 
         * @return PostType
         */
        static function getByID($postID)
        {
            if (empty($postID)) {
                throw new \InvalidArgumentException('No postID provided');
            }

            $class = get_called_class();
            $post = get_post($postID);

            if (empty($post) || $class::POST_SLUG !== $post->post_type) {
                throw new PostNotFoundException(
                    "Unable to find post with the ID #$postID"
                );
            }

            return $class::createFromPost($post);
        }

        /**
         * Gets all posts of type
         * 
         * @param array $args Arguments for get_posts
         * 
         * @return array
         */
        static public function getAll($args)
        {
            $class = get_called_class();
            wp_parse_args(
                $args,
                array('numberposts' => -1),
                $args
            );
            $args['post_type'] = $class::POST_SLUG;

            $posts = get_posts($args);

            foreach ($posts as &$post) {
                $post = $class::createFromPost($post);
            }

            return $posts;
        }


        static public function getAllByTitle()
        {
            return self::getAll(array('orderby' => 'post_title', 'order' => 'ASC'));
        }
        
        /**
         * Created an instance of Fund from a WP_Post object
         * 
         * @param WP_Post $post WP_Post object containing post details
         * 
         * @return [type]        [description]
         */
        public function createFromPost(\WP_Post $post)
        {
            $class = get_called_class();
            $newPost = new $class();
            $newPost->setPost($post);
            return $newPost;
        }

        /**
         * Adds any metaboxes that have been defined in the PostType subclass properties
         */
        static public function addMetaboxes()
        {
            $class = get_called_class();
            if (!empty($class::$metaboxes)) {
                foreach ($class::$metaboxes as $key => $settings) {
                    $metaboxCallbackMethod = 'metabox' . str_replace(
                        ' ',
                        '',
                        ucwords(
                            str_replace('_', ' ', $key)
                        )
                    );
                    $metaboxCallback = array($class, $metaboxCallbackMethod);
                    if (!is_callable($metaboxCallback)) {
                        throw new BadCallbackException(
                            "Callback $class::$metaboxCallbackMethod() not found"
                        );
                    }
                    $settings = shortcode_atts(
                        array(
                            'title' => ucwords($key),
                            'callback' => $metaboxCallback,
                            'context' => 'normal',
                            'priority' => 'default',
                            'callback_args' => null,
                        ),
                        $settings
                    );
                    add_meta_box(
                        $key,
                        $settings['title'],
                        $settings['callback'],
                        $class::POST_SLUG,
                        $settings['context'],
                        $settings['priority'],
                        $settings['callback_args']
                    );
                }
            }
        }



        /*********************
         * Getters & Setters *
         *********************/

        /**
         * Returns the WP_User object of the author of the post
         * 
         * @return WP_User
         */
        public function getAuthor()
        {
            $author = get_userdata($this->getPost()->post_author);

            return $author;
        }

        /**
         * Returns the post ID
         * 
         * @return int|false Returns ID if available, otherwise false
         */
        public function getID()
        {
            if (empty($this->WP_Post->ID)) {
                return false;
            }

            return (int) $this->WP_Post->ID;
        }

        /**
         * Synonymous method for getAuthor()
         *
         * @uses PostType::getAuthor()
         * 
         * @return WP_User
         */
        public function getOwner()
        {
            return $this->getAuthor();
        }

        /**
         * Sets the post property of the object
         * 
         * @param \WP_Post $fundPost WP_Post object
         */
        protected function setPost(\WP_Post $fundPost)
        {
            $this->WP_Post = $fundPost;
        }

        /**
         * Returns the WP_Post object for this object
         * 
         * @return WP_Post
         */
        protected function getPost()
        {
            return $this->WP_Post;
        }

        /**
         * Returns the post title
         * 
         * @return string
         */
        public function getTitle()
        {
            return $this->WP_Post->post_title;
        }

    } // END class PostType
} // END if (!class_exists('\CubicMushroom\WP\Core\PostType'))