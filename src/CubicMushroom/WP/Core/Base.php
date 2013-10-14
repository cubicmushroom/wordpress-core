<?php
/**
 * Base WordPress class for core functionality.  Is extended by the
 * CubicMushroom\WP'Plugins\Plugin and CubicMushroom\WP\Themes\Theme classes
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

/**
 * Base WordPress class for core functionality.  Is extended by the
 * CubicMushroom\WP\Plugins\Plugin and CubicMushroom\WP\Themes\Theme classes
 * 
 * @category   WordPress_Plugins
 * @package    CubicMushroom_WP
 * @subpackage Core
 * @author     Toby Griffiths <toby@cubicmushroom.co.uk>
 * @license    http://opensource.org/licenses/MIT MIT
 * @link       http://cubicmushroom.co.uk
 **/
class Base
{

    /*********************
     * Static properties *
     *********************/

    /**
     * Stores the instance of the plugin class to prevent duplicate objects being
     * created
     * @var Base
     */
    static protected $self;



    /*************************
     * Non-static properties *
     *************************/

    /**
     * The plugin's initial file.
     * 
     * This is used in various places such as activation & deactivation hooks
     * 
     * @var string
     */
    protected $coreFile;

    /**
     * Stores the object's actual class name
     * @var string
     */
    protected $class;

    /**
     * Flag to warn of incorrect descendant class setup
     * 
     * This flag set to true within Base::__construct(), & then checked within
     * Base::load() to verify that the descendant class has called the anscestor's
     * __constrt() methods
     *
     * @var bool
     */
    public $setupCorrectly = false;

    /**
     * Which hooks should we automatically look for callbacks for within the plugin
     * class?
     * @var array
     */
    protected $hooks = array(
        'init'       => 'hookInit',
        'admin_init' => 'hookAdminInit'
    );

    /**
     * Array of class names of classes extending the CubicMushroom\WP\Core\PostType
     * class, defining custom post types to be registered
     * @var array
     */
    protected $customPostTypes = array();

    /**
     * Array of custom user roles with the key as the role slug & the value as the
     * Role label
     *
     * This is user in the Base::hookActivationRolesAndCapabilities() method to
     * automatically add these on theme/plugin activation, & remove them again on
     * deactivation
     * 
     * @var array
     */
    protected $customRoles = array();

    /**
     * Array of custom user capabilities with the key as the role slug & the value as
     * an associated array containing the following keys...
     *
     *   * 'allow'           => array of roles grant this capability to
     *   * 'deny'            => array of roles to not grant this capability to
     *   * 'core_capability' => set to true if is a WP core capability, so it's not
     *                          removed when plugin is deactivated
     *
     * This is user in the Base::hookActivationRolesAndCapabilities() method to
     * automatically add these on theme/plugin activation, & remove them again on
     * deactivation
     * 
     * @var array
     */
    protected $customCapabilities = array();

    /**
     * Array of class names used to build metabox objects used within the plugin
     * @var array
     */
    protected $metaboxes = array();



    /******************
     * Static methods *
     ******************/

    /**
     * Instantiates plugin object, if not already instantiated, & return it
     *
     * @param string $file The plugin's initial file
     *
     * @throws \RuntimeException If $file is not provided when calling load() for the
     *                           first time
     * @throws \LogicException   If the descendant class does not call back to the
     *                           Base::__construct() method
     * 
     * @return Base
     */
    static function load($file = null)
    {
        if (empty(self::$self)) {
            $class = get_called_class();
            if (is_null($file)) {
                throw new \RuntimeException(
                    sprintf(
                        'You must pass in the main theme/plugin file path when ' .
                        'calling %s::load() for the plugin for the first time',
                        $class
                    )
                );
            }
            self::$self = new $class($file);
            if (!self::$self->setupCorrectly) {
                throw new \LogicException(
                    'Class not setup correctly, as it does not call anscestor '.
                    '__construct() methods back to Base class'
                );
            }
        }
        return self::$self;
    }


    /**********************
     * Non-static methods *
     **********************/

    /**
     * Protected constructor used to prevent multiple plugin objects being created.
     *
     * Use the Base::load() method to get the universal plugin
     * object
     *
     * @param string $file Theme or Plugin initial file
     */
    protected function __construct($file)
    {
        // Make object as setup correctly (check in self::load() after instantiation)
        $this->setupCorrectly = true;

        // Make sure the session has been started
        session_start();

        // Save the plugin file path
        $this->coreFile = $file;

        // Store the actual class name
        $this->class = get_class($this);

        // Register activation & deactivation hooks
        $this->registerActivationDeactivationUninstallHooks();

        // Flush the rewrite rules on activation
        register_activation_hook($this->coreFile, array(__CLASS__, 'flushRewriteRules'));

        // Check for main hood callbacks
        $this->setupAutomaticActionHooks();

        $this->registerMetaboxes();

        // Add plugin core hooks
        add_action('init', array($this, 'registerCustomPostTypes'), 9);
        add_action('admin_notices', array($this, 'displayAdminNotices'), 9);
    }

    /**
     * Checks for, & registers, if callable, hooks for activation, deactivation &
     * uninstall
     * 
     *  (Uninstall is not working at the moment.  Needs fixing)
     *
     * @throws \Exception If there is an uninstall hook defined, as this needs fixing
     *
     * @return  void
     */
    protected function registerActivationDeactivationUninstallHooks()
    {
        // Register plugin activation & deactivation hooks
        foreach (
            array('activation', 'deactivation', 'uninstall') as $pluginAction
        ) {
            $registerFunction = "register_{$pluginAction}_hook";

            // Firstly setup the base callbacks to automatically add/remove custom
            // roles & capabilities
            $rolesCapabilitiesCallback = array(
                __CLASS__,
                sprintf('hook%sRolesAndCapabilities', ucfirst($pluginAction))
            );

            call_user_func(
                $registerFunction,
                $this->coreFile,
                $rolesCapabilitiesCallback
            );


            // Now check the plugin/theme class for callback
            $callback = array(get_class($this), 'hook' . ucfirst($pluginAction));
            if (is_callable($callback)) {

                // For some reason the uninstall hook is not working, so throwing an 
                // exception if attempting to use this for now
                if ('uninstall' === $pluginAction) {
                    throw new \Exception(
                        'Attempting to use the uninstall hook, but this is not ' .
                        'working yet.  Please fix it!'
                    );
                }
                
                // Register the hook callback
                call_user_func(
                    $registerFunction,
                    $this->coreFile,
                    $callback
                );
            }
        }
    }

    /**
     * Flushes rewrite rules
     *
     * Is called during activation
     * 
     * @return void
     */
    static public function flushRewriteRules()
    {
        $class = get_called_class();
        $plugin = $class::load();

        // Call register post types to setup permalinks before flushing them
        $plugin->registerCustomPostTypes();

        // Call any additional pre-flush permalink setups
        do_action('cm_pre_flush');

        // Now flush the permalinks
        flush_rewrite_rules();
    }

    /**
     * Sets up automatic callbacks for certain action hooks
     * 
     * Uses $this->hooks to determine what callbacks we should look for to 
     * automatically be handled without needing to add manually.
     *
     * Will be setup with standard priority
     * 
     * @return void
     */
    protected function setupAutomaticActionHooks() {

        // We're going to add support for hook_* class method to automatically be
        // called, for the 2 main init hooks, if they exist
        foreach ($this->hooks as $hook => $func) {
            $callback = array($this, $func);
            if (is_callable($callback)) {
                add_action($hook, $callback);
            }
        }
    }

    /**
     * Instantiates metabox objects based on the classes metaboxes array property 
     *
     * @return void
     */
    protected function registerMetaboxes()
    {
        foreach ($this->metaboxes as $metabox) {
            
        }
    }

    /****************
     * Action hooks *
     ****************/

    /**
     * Registers plugin's custom post types
     *
     * This is called on the 'init' hook
     *
     * @uses __CLASS__::$customPostTypes This is an array of
     *       CubicMushroom\WP\Core\PostType class names to be registered
     *
     * @return void
     */
    public function registerCustomPostTypes()
    {
        if (!empty($this->customPostTypes)) {
            // Work out the namespace for the PostType classes
            $namespace = explode('\\', get_class($this));
            unset($namespace[count($namespace)-1]);
            $namespace[] = 'PostType';

            // Register the post types
            foreach ($this->customPostTypes as $postType) {
                $postTypeClass = implode('\\', $namespace) . "\\$postType";
                if (class_exists($postTypeClass)) {
                    try {
                        $postTypeClass::register($this);
                    } catch (PostTypeRegistrationFailedException $e) {
                        $this->addAdminNotice(
                            $e->getMessage(),
                            "load_post_type_failed:$postType"
                        );
                    }
                } else {
                    $this->addAdminNotice(
                        sprintf(
                            "Unable to load '%s' custom post type class. Please " .
                            "create the $postTypeClass class",
                            $postType
                        ),
                        "load_post_type_failed:$postType"
                    );
                }
            }
        }
    }

    /**
     * Sets up custom roles as defined in $this->customRoles property
     *
     * @throws \InvalidArgumentException If there is a grant type key that's not
     *                                   'allow' or 'deny'
     * 
     * @return void
     */
    static public function hookActivationRolesAndCapabilities()
    {
        $class = get_called_class();
        $plugin = $class::load();

        // Add custom roles
        if (!empty($plugin->customRoles)) {
            foreach ($plugin->customRoles as $key => $value) {
                add_role($key, $value);
            }
        }

        // Add custom capabilities
        if (!empty($plugin->customCapabilities)) {
            foreach ($plugin->customCapabilities as $capability => $grants) {
                foreach ($grants as $grant_type => $roles) {
                    switch($grant_type) {
                        case 'allow':
                            foreach ($roles as $role) {
                                $role = get_role($role);
                                $role->add_cap($capability, true);
                            }
                            break;
                        case 'deny':
                            foreach ($roles as $role) {
                                $role = get_role($role);
                                $role->add_cap($capability, false);
                            }
                            break;
                        case 'core_capability':
                            // Ignore this, as it's used to indicate whether the
                            // capability is a WP core capability, & therefore should
                            // not be removed on deactivation
                            break;
                        default:
                            throw new \InvalidArgumentException("Invalid grant type");
                            
                    }
                }
            }
        }
    }

    /**
     * Removes custom roles as defined in $this->customRoles property
     *
     * @throws \InvalidArgumentException If there is a grant type key that's not
     *                                   'allow' or 'deny'
     * 
     * @return void
     */
    static public function hookDeactivationRolesAndCapabilities()
    {
        global $wp_roles;

        $class = get_called_class();
        $plugin = $class::load();

        // Remove custom capabilities
        if (!empty($plugin->customCapabilities)) {
            foreach ($plugin->customCapabilities as $capability => $grants) {
                // If this is a WP core capability (core_capability = true) don't
                // remove
                if (!empty($grants['core_capability'])) {
                    continue;
                }

                foreach ($wp_roles->roles as $role => $details) {
                    $wp_roles->remove_cap($role, $capability);
                }
            }
        }

        // Remove custom roles
        if (!empty($plugin->customRoles)) {
            foreach ($plugin->customRoles as $key => $value) {
                remove_role($key);
            }
        }
    }

    /**
     * Displays any admin messages saved in the current session
     *
     * Notices are retrieved from $_SESSION['CM_WP'][\__CLASS__]['admin_notices']
     * 
     * @return void
     */
    public function displayAdminNotices()
    {
        $notices = @$_SESSION['CM_WP'][$this->class]['admin_notices'];
        if (!empty($notices)) {
            foreach ($notices as $notice) {
                printf(
                    '<div class="updated">%s</div>',
                    wpautop($notice)
                );
            }
        }
        unset($_SESSION['CM_WP'][$this->class]['admin_notices']);
    }


    /*******************
     * General methods *
     *******************/

    /**
     * Adds an admin notice ready for displaying when possible
     *
     * Notices are stored in $_SESSION['CM_WP'][\__CLASS__]['admin_notices']
     * 
     * @param string $msg Message to be displayed
     * @param string $key If set, will be used for the message key (preventing
     *                    multiple identical messages being displayed)
     *
     * @return void
     */
    protected function addAdminNotice($msg, $key = null)
    {
        $_SESSION['CM_WP'][$this->class]['admin_notices'][$key] = $msg;
    }

} // END class Base