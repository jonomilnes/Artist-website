<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/*
    Class: Child_Theme_Configurator
    Plugin URI: http://www.childthemeconfigurator.com/
    Description: Main Controller Class
    Version: 2.0.6
    Author: Lilaea Media
    Author URI: http://www.lilaeamedia.com/
    Text Domain: child-theme-configurator
    Domain Path: /lang
    License: GPLv2
    Copyright (C) 2014-2016 Lilaea Media
*/
class ChildThemeConfiguratorAdmin {

    // state
    var $genesis;
    var $reorder;
    var $processdone;
    var $childtype;
    var $template;
    var $is_ajax;
    var $is_get;
    var $is_post;
    var $skip_form;
    var $fs;
    var $encoding;

    var $fs_prompt;
    var $fs_method;
    var $uploadsubdir;
    var $menuName; // backward compatibility with plugin extension
    var $cache_updates  = TRUE;
    var $debug;
    var $is_debug       = 0;
    // memory checks
    var $max_sel;
    var $sel_limit;
    var $mem_limit;
    // state arrays
    var $themes         = array();
    var $errors         = array();
    var $files          = array();
    var $updates        = array();
    var $memory         = array();
    // objects
    var $css;
    var $ui;
    // config arrays
    var $postarrays     = array(
        'ctc_img',
        'ctc_file_parnt',
        'ctc_file_child',
        'ctc_additional_css',
    );
    var $configfields   = array(
        'theme_parnt', 
        'child_type', 
        'theme_child', 
        'child_template', 
        'child_name',
        'child_themeuri',
        'child_author',
        'child_authoruri',
        'child_descr',
        'child_tags',
        'child_version',
        'repairheader',
        'ignoreparnt',
        'handling',
        'enqueue',
        'configtype', // backward compatability - no longer used
    );
    var $actionfields   = array(
        'load_styles',
        'parnt_templates_submit',
        'child_templates_submit',
        'image_submit',
        'theme_image_submit',
        'theme_screenshot_submit',
        'export_child_zip',
        'export_theme',
        'reset_permission',
        'templates_writable_submit',
        'set_writable',
        'upgrade',
    );
    var $imgmimes       = array(
        'jpg|jpeg|jpe'  => 'image/jpeg',
        'gif'           => 'image/gif',
        'png'           => 'image/png',
    );

    function __construct() {
        $this->processdone  = FALSE;
        $this->genesis      = FALSE;
        $this->reorder      = FALSE;
        $this->is_new       = FALSE;
        $this->encoding     = WP_Http_Encoding::is_available();
        $this->menuName     = CHLD_THM_CFG_MENU; // backward compatability for plugins extension
        $this->is_post      = ( 'POST' == $_SERVER[ 'REQUEST_METHOD' ] );
        $this->is_get       = ( 'GET' == $_SERVER[ 'REQUEST_METHOD' ] );
        $this->is_debug     = get_option( CHLD_THM_CFG_OPTIONS . '_debug' );
        if ( $this->is_debug )
            $this->debug = get_site_transient( CHLD_THM_CFG_OPTIONS . '_debug' );
    }

    /**
     * initialize configurator
     */
    function ctc_page_init () {
        // get all available themes
        $this->get_themes();
        $this->childtype = count( $this->themes[ 'child' ] ) ? 'existing' : 'new';
    
        // load config data and validate
        $this->load_config();
        // perform any checks prior to processing config data
        do_action( 'chld_thm_cfg_preprocess' );
        // process any additional forms
        do_action( 'chld_thm_cfg_forms', $this );  // hook for custom forms
        // process main post data
        $this->process_post();
        // initialize UI
        include_once( CHLD_THM_CFG_DIR . '/includes/class-ctc-ui.php' );
        $this->ui = new ChildThemeConfiguratorUI();
        // initialize help
        $this->ui->render_help_content();
    }
    
    function render() {
        $this->ui->render();
    }

    /* helper function to retreive css object properties */
    function get( $property, $params = NULL ) {
        return $this->css->get_prop( $property, $params );
    }
    
    function get_themes() {
        // create cache of theme info
        $this->themes = array( 'child' => array(), 'parnt' => array() );
        foreach ( wp_get_themes() as $theme ):
            // organize into parent and child themes
            $group      = $theme->parent() ? 'child' : 'parnt';
            // get the theme slug
            $slug       = $theme->get_stylesheet();
            // get the theme slug
            $version    = $theme->get( 'Version' );
            // strip auto-generated timestamp from CTC child theme version
            if ( 'child' == $group ) $version = preg_replace("/\.\d{6}\d+$/", '', $version );
            // add theme to themes array
            $this->themes[ $group ][ $slug ] = array(
                'Template'      => $theme->get( 'Template' ),
                'Name'          => $theme->get( 'Name' ),
                'ThemeURI'      => $theme->get( 'ThemeURI' ),
                'Author'        => $theme->get( 'Author' ),
                'AuthorURI'     => $theme->get( 'AuthorURI' ),
                'Descr'         => $theme->get( 'Description' ),
                'Tags'          => $theme->get( 'Tags' ),
                'Version'       => $version,
                'screenshot'    => $theme->get_screenshot(),
                'allowed'       => $theme->is_allowed(),
            );
        endforeach;
    }

    function validate_post( $action = 'ctc_update', $noncefield = '_wpnonce', $cap = 'install_themes' ) {
        // security: request must be post, user must have permission, referrer must be local and nonce must match
        return ( $this->is_post 
            && current_user_can( $cap ) // ( 'edit_themes' )
            && ( $this->is_ajax ? check_ajax_referer( $action, $noncefield, FALSE ) : 
                check_admin_referer( $action, $noncefield, FALSE ) ) );
    }
    
    function load_config() {
        include_once( CHLD_THM_CFG_DIR . '/includes/class-ctc-css.php' );
        $this->css = new ChildThemeConfiguratorCSS();
        if ( FALSE !== $this->css->load_config() ):
            $this->debug( 'config exists', __FUNCTION__ );
            // if themes do not exist reinitialize
            if ( ! $this->check_theme_exists( $this->get( 'child' ) )
                || ! $this->check_theme_exists( $this->get( 'parnt' ) ) ):
            $this->debug( 'theme does not exist', __FUNCTION__ );
                add_action( 'admin_notices', array( $this, 'config_notice' ) );     
                $this->css = new ChildThemeConfiguratorCSS();
                $this->css->enqueue = 'enqueue';
            endif;
        else:
            $this->debug( 'config does not exist', __FUNCTION__ );
            // this is a fresh install
            $this->css->enqueue = 'enqueue';
        endif;
        do_action( 'chld_thm_cfg_load' );
        if ( $this->is_get ):
            if ( $this->get( 'child' ) ):
                // get filesystem credentials if available
                $this->verify_creds();
                $stylesheet = apply_filters( 
                    'chld_thm_cfg_target', 
                    $this->css->get_child_target( $this->get_child_stylesheet() ), 
                    $this->css );
                // check file permissions
                if ( !is_writable( $stylesheet ) && !$this->fs )
                    add_action( 'admin_notices', array( $this, 'writable_notice' ) );
                if ( $fsize = $this->get( 'fsize' ) ):
                    $test = filesize( $stylesheet );
                    $this->debug( 'filesize saved: ' . $fsize . ' current: ' . $test, __FUNCTION__ );
                    if ( $test != $fsize )
                        add_action( 'admin_notices', array( $this, 'changed_notice' ) );
                endif;
                // enqueue flag will be null for existing install < 1.6.0
                if ( !$this->get( 'enqueue' ) ):
                    $this->debug( 'no enqueue:', __FUNCTION__ );

                    add_action( 'admin_notices', array( $this, 'enqueue_notice' ) );     
                endif;
            endif;
            if ( !$this->seen_upgrade_notice() ):
                add_action( 'admin_notices', array( $this, 'upgrade_notice' ) ); 
            endif;
            /**
             * Future use: check if max selectors reached
             *
            if ( $this->get( 'max_sel' ) ):
                $this->debug( 'Max selectors exceeded.', __FUNCTION__ );
                //$this->errors[] = __( 'Maximum number of styles exceeded.', 'child-theme-configurator' );
                add_action( 'admin_notices', array( $this, 'max_styles_notice' ) ); 
            endif;
            */
            // check if file ownership is messed up from old version or other plugin
            // by comparing owner of plugin to owner of child theme:
            if ( fileowner( $this->css->get_child_target( '' ) ) != fileowner( CHLD_THM_CFG_DIR ) )
                add_action( 'admin_notices', array( $this, 'owner_notice' ) ); 
        endif;    
    }

    function cache_debug() {
        $this->updates[] = array(
            'obj'   => 'debug',
            'key'   => '',
            'data'  => $this->print_debug( TRUE ),
        );
    }
    /**
     * ajax callback for saving form data 
     */
    function ajax_save_postdata( $action = 'ctc_update' ) {
        $this->is_ajax = TRUE;
        
        // security check
        if ( $this->validate_post( $action ) ):
            if ( 'ctc_plugin' == $action ) do_action( 'chld_thm_cfg_pluginmode' );
            $this->verify_creds(); // initialize filesystem access
            // get configuration data from options API
            if ( FALSE !== $this->load_config() ): // sanity check: only update if config data exists
                if ( isset( $_POST[ 'ctc_is_debug' ] ) ):
                    // toggle debug
                    $this->toggle_debug();
                else:
                    $this->css->parse_post_data(); // parse any passed values
                    // if child theme config has been set up, save new data
                    // return recent edits and selected stylesheets as cache updates
                    if ( $this->get( 'child' ) ):
                        // hook for add'l plugin files and subdirectories
                        do_action( 'chld_thm_cfg_addl_files', $this );
                        $this->css->write_css();
                        // add any additional updates to pass back to browser
                        do_action( 'chld_thm_cfg_cache_updates' );
                        /*
                        $this->updates[] = array(
                            'obj'   => 'addl_css',
                            'key'   => '',
                            'data'  => $this->get( 'addl_css' ),
                        );
                        */
                    endif;
                    
                    // update config data in options API
                    $this->save_config();
                endif;
            endif;
        endif;
        $result = $this->css->obj_to_utf8( $this->updates );
        // send all updates back to browser to update cache
        die( json_encode( $result ) );
    }
    
    function save_config() {
        // update config data in options API
        $this->css->save_config();
    }
    
    /**
     * ajax callback to query config data 
     */
    function ajax_query_css( $action = 'ctc_update' ) {
        $this->is_ajax = TRUE;
        if ( $this->validate_post( $action ) ):
            if ( 'ctc_plugin' == $action ) do_action( 'chld_thm_cfg_pluginmode' );
            $this->load_config();
            $regex = "/^ctc_query_/";
            foreach( preg_grep( $regex, array_keys( $_POST ) ) as $key ):
                $name = preg_replace( $regex, '', $key );
                $param[ $name ] = sanitize_text_field( $_POST[ $key ] );
            endforeach;
            if ( !empty( $param[ 'obj' ] ) ):
                // add any additional updates to pass back to browser
                $this->updates[] = array(
                    'key'   => isset( $param[ 'key' ] ) ? $param[ 'key' ] : '',
                    'obj'   => $param[ 'obj' ],
                    'data'  => $this->get( $param[ 'obj' ], $param ),
                );
                do_action( 'chld_thm_cfg_cache_updates' );
                die( json_encode( $this->updates ) );
            endif;
        endif;
        die( 0 );
    }
    
    /**
     * check if user has been notified about upgrade 
     */
    function seen_upgrade_notice() {
        $seen_upgrade_version = get_user_meta( get_current_user_id(), 'chld_thm_cfg_upgrade_notice', TRUE );
        return version_compare( $seen_upgrade_version, CHLD_THM_CFG_PREV_VERSION, '>=' );
    }
    
    /**
     * ajax callback to dismiss upgrade notice 
     */
    function ajax_dismiss_notice( $action = 'ctc_update' ) {
        $this->is_ajax = TRUE;
        if ( $this->validate_post( $action ) ):
            update_user_meta( get_current_user_id(), 'chld_thm_cfg_upgrade_notice' , CHLD_THM_CFG_VERSION );
            $this->updates[] = array(
                'key'   => '',
                'obj'   => 'dismiss',
                'data'  => CHLD_THM_CFG_VERSION,
            );
            die( json_encode( $this->updates ) );
        endif;
        die( 0 );
    }

    /**
     * Handles processing for all form submissions.
     * Older versions ( < 1.6.0 ) smelled like spaghetti so we moved conditions 
     * to switch statement with the main setup logic in a separate function.
     */
    function process_post() {
        // make sure this is a post
        if ( $this->is_post ):
            // see if a valid action was passed
            foreach ( $this->actionfields as $field ):
                if ( in_array( 'ctc_' . $field, array_keys( $_POST ) ) ):
                    $actionfield = $field;
                    break;
                endif;
            endforeach;
            if ( empty( $actionfield ) ) return FALSE;
            
            // make sure post passes security checkpoint        
            $this->errors = array();
            if ( $this->validate_post( apply_filters( 'chld_thm_cfg_action', 'ctc_update' ) ) ):
                // reset debug log
                delete_site_transient( CHLD_THM_CFG_OPTIONS . '_debug' );
                // zip export does not require filesystem access so check that first
                    // handle uploaded file before checking filesystem
                    if ( 'theme_image_submit' == $actionfield && isset( $_FILES[ 'ctc_theme_image' ] ) ):
                        $this->handle_file_upload( 'ctc_theme_image', $this->imgmimes );          
                    elseif ( 'theme_screenshot_submit' == $actionfield && isset( $_FILES[ 'ctc_theme_screenshot' ] ) ):
                        $this->handle_file_upload( 'ctc_theme_screenshot', $this->imgmimes );
                    endif;
                    // now we need to check filesystem access 
                    $args = preg_grep( "/nonce/", array_keys( $_POST ), PREG_GREP_INVERT );
                    $this->verify_creds( $args );
                    if ( $this->fs ):
                        $msg = FALSE;
                        // we have filesystem access so proceed with specific actions
                        switch( $actionfield ):
                            case 'export_child_zip':
                            case 'export_theme':
                                $this->export_zip();
                                // if we get here the zip failed
                                $this->errors[] = __( 'Zip file creation failed.', 'child-theme-configurator' );
                                break;
                            case 'load_styles':
                                // main child theme setup function
                                $msg = $this->setup_child_theme();
                                break;
                            
                            case 'parnt_templates_submit':
                                // copy parent templates to child
                                if ( isset( $_POST[ 'ctc_file_parnt' ] ) ):
                                    foreach ( $_POST[ 'ctc_file_parnt' ] as $file ):
                                        $this->copy_parent_file( sanitize_text_field( $file ) );
                                    endforeach;
                                    $msg = '8&tab=file_options';
                                endif;
                                break;
                                
                            case 'child_templates_submit':
                                // delete child theme files
                                if ( isset( $_POST[ 'ctc_file_child' ] ) ):
                                    if ( in_array( 'functions', $_POST[ 'ctc_file_child' ] ) ):
                                        $this->errors[] = 
                                            __( 'The Functions file is required and cannot be deleted.', 'child-theme-configurator' );
                                    else:
                                        foreach ( $_POST[ 'ctc_file_child' ] as $file ):
                                            $this->delete_child_file( sanitize_text_field( $file ), 
                                                ( preg_match( "/^style|ctc\-plugins/", $file ) ? 'css' : 'php' ) );
                                        endforeach;
                                        $msg = '8&tab=file_options';
                                    endif;
                                endif;
                                break;
                                
                            case 'image_submit':
                                // delete child theme images
                                if ( isset( $_POST[ 'ctc_img' ] ) ):
                                    foreach ( $_POST[ 'ctc_img' ] as $file ):
                                        $this->delete_child_file( 'images/' . sanitize_text_field( $file ), 'img' );
                                    endforeach;
                                    $msg = '8&tab=file_options';
                                endif;
                                break;
                                
                            case 'templates_writable_submit':
                                // make specific files writable ( systems not running suExec )
                                if ( isset( $_POST[ 'ctc_file_child' ] ) ):
                                    foreach ( $_POST[ 'ctc_file_child' ] as $file ):
                                        $this->set_writable( sanitize_text_field( $file ), 
                                            ( 0 === strpos( $file, 'style' ) ? 'css' : 'php' ) );
                                    endforeach;
                                    $msg = '8&tab=file_options';
                                endif;
                                break;
                                
                            case 'set_writable':
                                // make child theme style.css and functions.php writable ( systems not running suExec )
                                $this->set_writable(); // no argument defaults to style.css
                                $this->set_writable( 'functions' );
                                $msg = '8&tab=file_options';
                                break;
                            
                            case 'reset_permission':
                                // make child theme read-only ( systems not running suExec )
                                $this->unset_writable();
                                $msg = '8&tab=file_options';
                                break;
                            
                            case 'theme_image_submit':
                                // move uploaded child theme images (now we have filesystem access)
                                if ( isset( $_POST[ 'movefile' ] ) ):
                                    $this->move_file_upload( 'images' );
                                    $msg = '8&tab=file_options';
                                endif;
                                break;
                            
                            case 'theme_screenshot_submit':
                                // move uploaded child theme screenshot (now we have filesystem access)
                                if ( isset( $_POST[ 'movefile' ] ) ):
                                    // remove old screenshot
                                    foreach( array_keys( $this->imgmimes ) as $extreg ): 
                                        foreach ( explode( '|', $extreg ) as $ext ):
                                            $this->delete_child_file( 'screenshot', $ext );
                                        endforeach; 
                                    endforeach;
                                    $this->move_file_upload( '' );
                                    $msg = '8&tab=file_options';
                                endif;
                                break;
                            default:
                                // assume we are on the files tab so just redirect there
                                $msg = '8&tab=file_options';
                        endswitch;
                    endif; // end filesystem condition
                if ( empty( $this->errors ) && empty( $this->fs_prompt ) ):
                    $this->processdone = TRUE;
                    //die( '<pre><code><small>' . print_r( $_POST, TRUE ) . '</small></code></pre>' );
                    // no errors so we redirect with confirmation message
                    $this->update_redirect( $msg );
                endif;
                // otherwise fail gracefully
                $msg = NULL;
                return FALSE;
            endif; // end post validation condition
            // if you end up here you are persona non grata
            $msg = NULL;
            $this->errors[] = __( 'You do not have permission to configure child themes.', 'child-theme-configurator' );
        endif; // end request method condition
        return FALSE;
    }
    
    /**
     * Handle the creation or update of a child theme
     */
    function setup_child_theme() {
        $msg = 1;
        // sanitize and extract config fields into local vars
        foreach ( $this->configfields as $configfield ):
            $varparts = explode( '_', $configfield );
            $varname = end( $varparts );
            ${$varname} = empty( $_POST[ 'ctc_' . $configfield ] ) ? '' : 
                preg_replace( "/\s+/s", ' ', sanitize_text_field( $_POST[ 'ctc_' . $configfield ] ) );
            $this->debug( 'Extracting var ' . $varname . ' from ctc_' . $configfield . ' value: ' . ${$varname} , __FUNCTION__ );
        endforeach;
        
        if ( isset( $type ) ) $this->childtype = $type;
        
        // legacy plugin extension needs parent/new values but this version disables the inputs
        // so get we them from current css object
        if ( !$this->is_theme( $configtype ) && $this->is_legacy() ):
            $parnt  = $this->get( 'parnt' );
            $child  = $this->get( 'child' );
            $name   = $this->get( 'child_name' );
        endif;        
        // validate parent and child theme inputs
        if ( $parnt ):
            if ( ! $this->check_theme_exists( $parnt ) ):
                $this->errors[] = sprintf( 
                    __( '%s does not exist. Please select a valid Parent Theme.', 'child-theme-configurator' ), $parnt );
            endif;
        else:
            $this->errors[] = __( 'Please select a valid Parent Theme.', 'child-theme-configurator' );
        endif;

        // if this is reset, duplicate or existing, we must have a child theme
        if ( 'new' != $type && empty( $child ) ):
            $this->errors[] = __( 'Please select a valid Child Theme.', 'child-theme-configurator' );
        // if this is a new or duplicate child theme we must validate child theme directory
        elseif ( 'new' == $type || 'duplicate' == $type ):
            if ( empty( $template ) && empty( $name ) ):
                $this->errors[] = __( 'Please enter a valid Child Theme directory name.', 'child-theme-configurator' );
            else:
                $template_sanitized = preg_replace( "%[^\w\-]%", '', empty( $template ) ? $name : $template );
                if ( $this->check_theme_exists( $template_sanitized ) ):
                    $this->errors[] = sprintf( 
                        __( '<strong>%s</strong> exists. Please enter a different Child Theme template name.', 'child-theme-configurator' ), $template_sanitized );
                elseif ( 'duplicate' == $type ):
                    // clone existing child theme
                    $this->clone_child_theme( $child, $template_sanitized );
                    if ( !empty( $this->errors ) ) return FALSE;
                    // if no errors, copy menus, widgets and customizer options
                    $this->copy_theme_mods( $child, $template_sanitized );
                    $msg = 3;
                else:
                    $msg = 2;
                endif;
                $child = $template_sanitized;
            endif;
        
        endif;
            
        // verify_child_dir creates child theme directory if it doesn't exist.
        if ( FALSE === $this->verify_child_dir( $child ) ):
            // if it returns false then it could not create directory.
            $this->errors[] = __( 'Your theme directories are not writable.', 'child-theme-configurator' );
            add_action( 'admin_notices', array( $this, 'writable_notice' ) );     
            return FALSE;
        endif;
    
        // load or reset config
        if ( 'reset' == $type ):
            $this->debug( 'resetting child theme', __FUNCTION__ );
            $this->reset_child_theme();
            $this->enqueue_parent_css( $this );
            $msg = 4;
        else:

            // if any errors, bail before we create css object
            if ( !empty( $this->errors ) ) return FALSE;
            
            // if no name is passed, create one from the child theme directory
            if ( empty( $name ) ):
                $name = ucfirst( $child );
            endif;
    
            /**
             * before we configure the child theme we need to check if this is a rebuild
             * and compare some of the original settings to the new settings.
             */
            //$oldchild           = $this->get( 'child' );
            //$oldimports         = $this->get( 'imports' );
            //$oldenqueue         = $this->get( 'enqueue' );
            $oldhandling        = $this->get( 'handling' );
            // reset everything else
            $this->css          = new ChildThemeConfiguratorCSS();
            // restore imports if this is a rebuild
            //$this->css->imports[ 'child' ] = $oldimports;
            
            // update with new parameters
            if ( !$this->is_theme( $configtype ) )
                $this->css->set_prop( 'enqueue', 'enqueue' );
            else
                $this->css->set_prop( 'enqueue',            $enqueue );
            $this->css->set_prop( 'handling',           $handling );
            $this->css->set_prop( 'ignoreparnt',        $ignoreparnt );
    
            $this->css->set_prop( 'parnt',              $parnt );
            $this->css->set_prop( 'child',              $child );
            $this->css->set_prop( 'child_name',         $name );
            $this->css->set_prop( 'child_author',       $author );
            $this->css->set_prop( 'child_themeuri',     $themeuri );
            $this->css->set_prop( 'child_authoruri',    $authoruri );
            $this->css->set_prop( 'child_descr',        $descr );
            $this->css->set_prop( 'child_tags',         $tags );
            $this->css->set_prop( 'child_version',      strlen( $version ) ? $version : '1.0' );
    
            if ( isset( $_POST[ 'ctc_action' ] ) && 'plugin' == $_POST[ 'ctc_action' ] ):
                // this is for PRO plugins
                $this->css->addl_css = array();
                if ( isset( $_POST[ 'ctc_additional_css' ] ) && is_array( $_POST[ 'ctc_additional_css' ] ) ): 
                    foreach ( $_POST[ 'ctc_additional_css' ] as $file )
                        $this->css->addl_css[] = sanitize_text_field( $file );
                endif;
                add_action( 'chld_thm_cfg_parse_stylesheets', array( &$this, 'parse_child_stylesheet_to_target' ) );
            elseif ( isset( $_POST[ 'ctc_analysis' ] ) ):
                // this is for themes
                $this->evaluate_signals();
            endif;
            // roll back CTC Pro Genesis handling option
            if ( $this->genesis ):
                $handling       = 'separate';
                $enqueue        = 'none';
                $ignoreparnt    = TRUE;
                if ( $this->backup_or_restore_file( 'ctc-style.css', TRUE, 'style.css' ) &&
                    $this->backup_or_restore_file( 'style.css', TRUE, 'ctc-genesis.css' ) ):
                    $this->delete_child_file( 'ctc-genesis', 'css' );
                else:
                    $this->errors[] = __( 'Could not upgrade child theme', 'child-theme-configurator' );
                endif;
            endif;

            // if any errors, bail before we set action hooks or write to filesystem
            if ( !empty( $this->errors ) ) return FALSE;
    
            // override enqueue action for parent theme if it is already being loaded
            if ( 'enqueue' == $enqueue && ( $this->get( 'parntloaded' ) || !$this->get( 'hasstyles' ) || $ignoreparnt ) ) $enqueue = 'none';
        
            // parse parent stylesheet if theme or legacy plugin extension 
            if ( $this->is_theme( $configtype ) || $this->is_legacy() ):
                // do we parse parent stylesheet?
                
                if ( $this->get( 'hasstyles' ) && !$ignoreparnt ):
                    $this->debug( 'Adding action: parse_parent_stylesheet_to_source', __FUNCTION__ );
                    add_action( 'chld_thm_cfg_parse_stylesheets', array( &$this, 'parse_parent_stylesheet_to_source' ) );
                endif;
                
                // automatically network enable new theme // FIXME: shouldn't this be an option?
                if ( is_multisite() )
                    add_action( 'chld_thm_cfg_addl_options', array( &$this, 'network_enable' ) );
            endif;
            $this->debug( 'Adding action: parse_additional_stylesheets_to_source', __FUNCTION__ );
            add_action( 'chld_thm_cfg_parse_stylesheets', array( &$this, 'parse_additional_stylesheets_to_source' ) );
        
            if ( 'separate' == $handling ):
                // parse child theme style.css into source config
                $this->debug( 'Adding action: parse_child_stylesheet_to_source', __FUNCTION__ );
                add_action( 'chld_thm_cfg_parse_stylesheets', array( &$this, 'parse_child_stylesheet_to_source' ) );
                // parse child theme ctc-style.css into target config
                $this->debug( 'Adding action: parse_custom_stylesheet_to_target', __FUNCTION__ );
                add_action( 'chld_thm_cfg_parse_stylesheets', array( &$this, 'parse_custom_stylesheet_to_target' ) );
            elseif ( 'primary' == $handling ):
                // parse child theme style.css into target config
                $this->debug( 'Adding action: parse_child_stylesheet_to_target', __FUNCTION__ );
                add_action( 'chld_thm_cfg_parse_stylesheets', array( &$this, 'parse_child_stylesheet_to_target' ) );
                if ( $oldhandling != $handling ):
                    $this->debug( 'Adding action: parse_custom_stylesheet_to_target', __FUNCTION__ );
                    add_action( 'chld_thm_cfg_parse_stylesheets', array( &$this, 'parse_custom_stylesheet_to_target' ) );
                endif;
            endif;
            
            // function to support wp_filesystem requirements
            if ( $this->is_theme( $configtype ) ):
                // is theme means this is not a plugin stylesheet config
                add_action( 'chld_thm_cfg_addl_files', array( &$this, 'add_base_files' ), 10, 2 );
                add_action( 'chld_thm_cfg_addl_files', array( &$this, 'copy_screenshot' ), 10, 2 );
                add_action( 'chld_thm_cfg_addl_files', array( &$this, 'enqueue_parent_css' ), 15, 2 );
                if ( $repairheader && 'reset' != $type ):
                    add_action( 'chld_thm_cfg_addl_files', array( &$this, 'repair_header' ) );
                endif;
                
            // legacy -- do we still need???
            elseif( $this->is_legacy() && has_action( 'chld_thm_cfg_addl_files' ) ):
                // backwards compatability for plugins extension < 2.0.0 (before pro)
                // action exists so we have to hijack it to use new filesystem checks
                remove_all_actions( 'chld_thm_cfg_addl_files' );
                add_action( 'chld_thm_cfg_addl_files', array( &$this, 'write_addl_files' ), 10, 2 );
                $this->css->set_prop( 'configtype', $configtype );
            endif;
    
            // plugin hooks for additional stylesheet handling options
                // do_action( 'chld_thm_cfg_stylesheet_handling' );
                // do_action( 'chld_thm_cfg_existing_theme' );
                
            // plugin hook to parse additional or non-standard files
            do_action( 'chld_thm_cfg_parse_stylesheets' );
    
            // copy menus, widgets and other customizer options from parent to child if selected
            if ( isset( $_POST[ 'ctc_parent_mods' ] ) && 'duplicate' != $type )
                $this->copy_theme_mods( $parnt, $child );
            // run code generation function in read-only mode to add existing external stylesheet links to config data
            $this->enqueue_parent_css( $this->css, TRUE );
            // hook for add'l plugin files and subdirectories. Must run after stylesheets are parsed to apply latest options
            do_action( 'chld_thm_cfg_addl_files', $this );
            // do not continue if errors 
            if ( !empty ( $this->errors ) ) return FALSE;
            //echo ' no errors! saving...' . LF;
            if ( 'separate' == $handling ):
                $this->debug( 'Writing new stylesheet header...', __FUNCTION__ );
                $this->rewrite_stylesheet_header();
            endif;
            // set flag to skip import link conversion on ajax save
            $this->css->set_prop( 'converted', 1 );
            
            // try to write new stylsheet. If it fails send alert.
            $this->debug( 'Writing new CSS...', __FUNCTION__ );
            if ( FALSE === $this->css->write_css() ):
                //$this->debug( print_r( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), TRUE ), __FUNCTION__ );
                $this->errors[] = __( 'Your stylesheet is not writable.', 'child-theme-configurator' );
                add_action( 'admin_notices', array( $this, 'writable_notice' ) );     
                return FALSE;
            endif; 
            // get files to reload templates in new css object
            $this->get_files( $parnt );
        endif;
        $this->debug( 'Saving new config...', __FUNCTION__ );
        // save new object to WP options table
        $this->save_config();
        $this->debug( 'Firing additional options action...', __FUNCTION__ );
        // plugin hook for additional child theme setup functions
        do_action( 'chld_thm_cfg_addl_options', $this );
        
        // return message id 1, which says new child theme created successfully;
        return $msg;
    }

    /*
     * TODO: this is a stub for future use
     */
    function sanitize_options( $input ) {
        return $input;
    }
    
    /**
     * remove slashes and non-alphas from stylesheet name
     */
    function sanitize_slug( $slug ) {
        return preg_replace( "/[^\w\-]/", '', $slug );
    }
    
    function update_redirect( $msg = 1 ) {
        $this->print_debug( TRUE );
        if ( empty( $this->is_ajax ) ):
            $ctcpage = apply_filters( 'chld_thm_cfg_admin_page', CHLD_THM_CFG_MENU );
            $screen = get_current_screen()->id;
            wp_safe_redirect(
                ( strstr( $screen, '-network' ) ? network_admin_url( 'themes.php' ) : admin_url( 'tools.php' ) ) 
                    . '?page=' . $ctcpage . ( $msg ? '&updated=' . $msg : '' ) );
            die();
        endif;
    }
    
    function verify_child_dir( $path ) {
        $this->debug( 'Verifying child dir: ' . $path, __FUNCTION__ );
        if ( !$this->fs ): 
            $this->debug( 'No filesystem access.', __FUNCTION__ );
            return FALSE; // return if no filesystem access
        endif;
        global $wp_filesystem;
        $themedir = $wp_filesystem->find_folder( get_theme_root() );
        if ( ! $wp_filesystem->is_writable( $themedir ) ):
            $this->debug( 'Directory not writable: ' . $themedir, __FUNCTION__ );
            return FALSE;
        endif;
        $childparts = explode( '/', $this->normalize_path( $path ) );
        while ( count( $childparts ) ):
            $subdir = array_shift( $childparts );
            if ( empty( $subdir ) ) continue;
            $themedir = trailingslashit( $themedir ) . $subdir;
            if ( ! $wp_filesystem->is_dir( $themedir ) ):
                if ( ! $wp_filesystem->mkdir( $themedir, FS_CHMOD_DIR ) ):
                $this->debug( 'Could not make directory: ' . $themedir, __FUNCTION__ );
                    return FALSE;
                endif;
            elseif ( ! $wp_filesystem->is_writable( $themedir ) ):
                $this->debug( 'Directory not writable: ' . $themedir, __FUNCTION__ );
                return FALSE;
            endif;
        endwhile;
        $this->debug( 'Child dir verified: ' . $themedir, __FUNCTION__ );
        return TRUE;
    }
    
    function add_base_files( $obj ){
        //$this->debug( LF . LF, __FUNCTION__ );
        // add functions.php file
        $contents = "<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;
";
        $handling = $this->get( 'handling' );
        $this->write_child_file( 'functions.php', $contents );
        $this->backup_or_restore_file( 'style.css' );
        $contents = $this->css->get_css_header_comment( $handling );
        $this->debug( 'writing initial stylesheet header...' . LF . $contents, __FUNCTION__ );
        $this->write_child_file( 'style.css', $contents );
        if ( 'separate' == $handling ):
            $this->backup_or_restore_file( 'ctc-style.css' );
            $this->write_child_file( 'ctc-style.css', $contents . LF );
        endif;
    }
    
    // parses @import syntax and converts to wp_enqueue_style statement
    function convert_import_to_enqueue( $import, $count, $execute = FALSE ) {
        $relpath    = $this->get( 'child' );
        $import     = preg_replace( "#^.*?url\(([^\)]+?)\).*#", "$1", $import );
        $import     = preg_replace( "#[\'\"]#", '', $import );
        $path       = $this->css->convert_rel_url( trim( $import ), $relpath , FALSE );
        $abs        = preg_match( '%(https?:)?//%', $path );
        if ( $execute )
            wp_enqueue_style( 'chld_thm_cfg_ext' . $count,  $abs ? $path : trailingslashit( get_theme_root_uri() ) . $path );
        else
            return "wp_enqueue_style( 'chld_thm_cfg_ext" . $count . "', " 
                . ( $abs ? "'" . $path . "'" : "trailingslashit( get_theme_root_uri() ) . '" . $path . "'" ) . ' );';
    }
    
    // converts enqueued path into @import statement for config settings
    function convert_enqueue_to_import( $path ) {
        if ( preg_match( '%(https?:)?//%', $path ) ):
            $this->css->imports[ 'child' ]['@import url(' . $path . ')'] = 1;
            return;
        endif;
        $regex  = '#^' . preg_quote( trailingslashit( $this->get( 'child' ) ) ) . '#';
        $path   = preg_replace( $regex, '', $path, -1, $count );
        if ( $count ): 
            $this->css->imports[ 'child' ]['@import url(' . $path . ')'] = 1;
            return;
        endif;
        $parent = trailingslashit( $this->get( 'parnt' ) );
        $regex  = '#^' . preg_quote( $parent ) . '#';
        $path   = preg_replace( $regex, '../' . $parent, $path, -1, $count );
        if ( $count )
            $this->css->imports[ 'child' ]['@import url(' . $path . ')'] = 1;
    }
    
    /**
     * Generates wp_enqueue_script code block for child theme functions file
     * Enqueues parent and/or child stylesheet depending on value of 'enqueue' setting.
     * If external imports are present, it enqueues them as well.
     */
    function enqueue_parent_code(){
        //$this->debug( print_r( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), TRUE ), __FUNCTION__ );
        $imports        = $this->get( 'imports' );
        $enqueues       = array();
        $code           = "// AUTO GENERATED - Do not modify or remove comment markers above or below:" . LF;
        $deps           = $this->get( 'parnt_deps' );
        $enq            = $this->get( 'enqueue' );
        $handling       = $this->get( 'handling' );
        $hasstyles      = $this->get( 'hasstyles' );
        $childloaded    = $this->get( 'childloaded' );
        $parntloaded    = $this->get( 'parntloaded' );
        $cssunreg       = $this->get( 'cssunreg' );
        $csswphead      = $this->get( 'csswphead' );
        $cssnotheme     = $this->get( 'cssnotheme' );
        $ignoreparnt    = $this->get( 'ignoreparnt' );
        $priority       = $this->get( 'qpriority' );
        $this->debug( 'deps: ' . print_r( $deps, TRUE ) . ' enq: ' . $enq . ' handling: ' . $handling
            . ' hasstyles: ' . $hasstyles . ' parntloaded: ' . $parntloaded . ' childloaded: ' . $childloaded
            . ' ignoreparnt: ' . $ignoreparnt . ' priority: ' . $priority . ' childtype: ' . $this->childtype, __FUNCTION__ );
         // enqueue parent stylesheet 
        if ( 'enqueue' == $enq && $hasstyles && !$parntloaded && !$ignoreparnt ):
            $code .= "
if ( !function_exists( 'chld_thm_cfg_parent_css' ) ):
    function chld_thm_cfg_parent_css() {
        wp_enqueue_style( 'chld_thm_cfg_parent', trailingslashit( get_template_directory_uri() ) . 'style.css', array( " . implode( ',', $deps ) . " ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'chld_thm_cfg_parent_css', " . $priority . " );
";
            // if loading parent theme, reset deps and add parent stylesheet
            $deps = array( "'chld_thm_cfg_parent'" );
            
            // force a stylesheet dependency if parent is loading out of sequence
            if ( $childloaded && $this->reorder ):
                $code .= "
if ( !function_exists( 'chld_thm_cfg_add_parent_dep' ) ):
    function chld_thm_cfg_add_parent_dep() {
        global \$wp_styles;
        array_unshift( \$wp_styles->registered[ '" . $childloaded . "' ]->deps, 'chld_thm_cfg_parent' );
    }
endif;
add_action( 'wp_head', 'chld_thm_cfg_add_parent_dep', 2 );
";
            endif;
        endif;
        // enqueue external stylesheets (previously used @import in the stylesheet)
        if ( !empty( $imports ) ):
            $ext = 0;
            foreach ( $imports as $import ):
                if ( !empty( $import ) ):
                    $ext++;
                    $enqueues[] = '        ' . $this->convert_import_to_enqueue( $import, $ext ); 
                endif;
            endforeach;
        endif;
        // if child not loaded, enqueue it and add it to dependencies
        if ( 'separate' != $handling && ( ( $csswphead || $cssunreg || $cssnotheme ) 
            || ( 'new' != $this->childtype && !$childloaded ) 
            ) ): 
            $deps = array_merge( $deps, $this->get( 'child_deps' ) );
            $enqueues[] = "        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( " . implode( ',', $deps ) . " ) );";
            // if loading child theme stylesheet, reset deps and add child stylesheet
            $deps = array( "'chld_thm_cfg_child'" );
        endif;
        if ( 'separate' == $handling ):
            $deps = array_merge( $deps, $this->get( 'child_deps' ) );
            $enqueues[] = "        wp_enqueue_style( 'chld_thm_cfg_separate', trailingslashit( get_stylesheet_directory_uri() ) . 'ctc-style.css', array( " . implode( ',', $deps ) . " ) );";
        endif;
        if ( count( $enqueues ) ):
            $code .= "         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {" . LF;
            $code .= implode( "\n", $enqueues );
            $code .= "
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css' );" . LF;
        endif;
        if ( $ignoreparnt )
            $code .= "
defined( 'CHLD_THM_CFG_IGNORE_PARENT' ) or define( 'CHLD_THM_CFG_IGNORE_PARENT', TRUE );" . LF;
        //$this->debug( $code, __FUNCTION__ );
        return explode( "\n", $code ); // apply_filters( 'chld_thm_cfg_enqueue_code_filter', $code ) ); // FIXME?
    }
    
    // updates function file with wp_enqueue_script code block. If getexternals flag is passed function is run in read-only mode
    function enqueue_parent_css( $obj, $getexternals = FALSE ) {
        $this->debug( 'enqueueing parent css: getexternals = ' . $getexternals, __FUNCTION__ );
        $marker  = 'ENQUEUE PARENT ACTION';
        $insertion  =  $this->enqueue_parent_code();
        if ( $filename   = $this->css->is_file_ok( $this->css->get_child_target( 'functions.php' ), 'write' ) ):
            $this->insert_with_markers( $filename, $marker, $insertion, $getexternals );
            /// FIXME - reset for Pro version
            if ( !$getexternals && 'reset' == $this->childtype ):
                $marker  = 'CTC ENQUEUE PLUGIN ACTION';
                $this->insert_with_markers( $filename, $marker, array() );
            endif;
        endif;
    }
    
    /**
     * Update functions file with wp_enqueue_style code block. Runs in read-only mode if getexternals is passed.
     * This function uses the same method as the WP core function that updates .htaccess 
     * we would have used WP's insert_with_markers function, 
     * but it does not use wp_filesystem API.
     */
    function insert_with_markers( $filename, $marker, $insertion, $getexternals = FALSE ) { 
        if ( count( $this->errors ) ):
            $this->debug( 'Errors detected, returning', __FUNCTION__ );
            return FALSE;
        endif;
        // first check if this is an ajax update
        if ( $this->is_ajax && is_readable( $filename ) && is_writable( $filename ) ):
            // ok to proceed
            $this->debug( 'Ajax update, bypassing wp filesystem.', __FUNCTION__ );
            $markerdata = explode( "\n", @file_get_contents( $filename ) );
        elseif ( !$this->fs ): 
            $this->debug( 'No filesystem access.', __FUNCTION__ );
            return FALSE; // return if no filesystem access
        else:
            global $wp_filesystem;
            if( !$wp_filesystem->exists( $this->fspath( $filename ) ) ):
                if ( $getexternals ):
                    $this->debug( 'Read only and no functions file yet, returning...', __FUNCTION__ );
                    return FALSE;
                else:
                    // make sure file exists with php header
                    $this->debug( 'No functions file, creating...', __FUNCTION__ );
                    $this->add_base_files( $this );
                endif;
            endif;
            // get_contents_array returns extra linefeeds so just split it ourself
            $markerdata = explode( "\n", $wp_filesystem->get_contents( $this->fspath( $filename ) ) );
        endif;
        $newfile = '';
        $externals  = array();
        $phpopen    = 0;
        $in_comment = 0;
        $foundit = FALSE;
        if ( $markerdata ):
            $state = TRUE;
            foreach ( $markerdata as $n => $markerline ):
                // remove double slash comment to end of line
                $str = preg_replace( "/\/\/.*$/", '', $markerline );
                preg_match_all("/(<\?|\?>|\*\/|\/\*)/", $str, $matches );
                if ( $matches ):
                    foreach ( $matches[1] as $token ): 
                        if ( '/*' == $token ):
                            $in_comment = 1;
                        elseif ( '*/' == $token ):
                            $in_comment = 0;
                        elseif ( '<?' == $token && !$in_comment ):
                            $phpopen = 1;
                        elseif ( '?>' == $token && !$in_comment ):
                            $phpopen = 0;
                        endif;
                    endforeach;
                endif;
                if ( strpos( $markerline, '// BEGIN ' . $marker ) !== FALSE )
                    $state = FALSE;
                if ( $state ):
                    if ( $n + 1 < count( $markerdata ) )
                        $newfile .= "{$markerline}\n";
                    else
                        $newfile .= "{$markerline}";
                elseif ( $getexternals ):
                    // look for existing external stylesheets and add to imports config data
                    if ( preg_match( "/wp_enqueue_style.+?'chld_thm_cfg_ext\d+'.+?'(.+?)'/", $markerline, $matches ) ):
                        $this->debug( 'external link found : ' . $matches[ 1 ] );
                        $this->convert_enqueue_to_import( $matches[ 1 ] );
                    endif;
                endif;
                if ( strpos( $markerline, '// END ' . $marker ) !== FALSE ):
                    if ( 'reset' != $this->childtype ):
                        $newfile .= "// BEGIN {$marker}\n";
                        if ( is_array( $insertion ) )
                            foreach ( $insertion as $insertline )
                                $newfile .= "{$insertline}\n";
                        $newfile .= "// END {$marker}\n";
                    endif;
                    $state = TRUE;
                    $foundit = TRUE;
                endif;
            endforeach;
        else:
            $this->debug( 'Could not parse functions file', __FUNCTION__ );
            return FALSE;
        endif;
        if ( $foundit ):
            $this->debug( 'Found marker, replaced inline', __FUNCTION__ );
        else:
            if ( 'reset' != $this->childtype ):
                // verify there is no PHP close tag at end of file
                if ( ! $phpopen ):
                    $this->debug( 'PHP not open', __FUNCTION__ );
                    //$this->errors[] = __( 'A closing PHP tag was detected in Child theme functions file so "Parent Stylesheet Handling" option was not configured. Closing PHP at the end of the file is discouraged as it can cause premature HTTP headers. Please edit <code>functions.php</code> to remove the final <code>?&gt;</code> tag and click "Generate/Rebuild Child Theme Files" again.', 'child-theme-configurator' );
                    //return FALSE;
                    $newfile .= '<?php' . LF;
                endif;
                $newfile .= "\n// BEGIN {$marker}\n";
                foreach ( $insertion as $insertline )
                    $newfile .= "{$insertline}\n";
                $newfile .= "// END {$marker}\n";
            endif;
        endif;
        // only write file when getexternals is false
        if ( $getexternals ):
            $this->debug( 'Read only, returning.', __FUNCTION__ );
        else:
            $mode = 'direct' == $this->fs_method ? FALSE : 0666;
            $this->debug( 'Writing new functions file...', __FUNCTION__ );
            if ( $this->is_ajax && is_writable( $filename ) ): 
                // with ajax we have to bypass wp filesystem so file must already be writable
                if ( FALSE === @file_put_contents( $filename, $newfile ) ): 
                    $this->debug( 'Ajax write failed.', __FUNCTION__ );
                    return FALSE;
                endif;
            elseif ( FALSE === $wp_filesystem->put_contents( 
                $this->fspath( $filename ), 
                $newfile, 
                $mode 
            ) ): // chmod will fail unless we have fs access. user can secure files after configuring
                $this->debug( 'Filesystem write failed.', __FUNCTION__ );
                return FALSE;
            endif;
            $this->css->set_prop( 'converted', 1 );
        endif;
    }
    
    // creates/updates file via filesystem API
    function write_child_file( $file, $contents ) {
        //$this->debug( LF . LF, __FUNCTION__ );
        if ( !$this->fs ): 
            $this->debug( 'No filesystem access, returning.', __FUNCTION__ );
            return FALSE; // return if no filesystem access
        endif;
        global $wp_filesystem;
        if ( $file = $this->css->is_file_ok( $this->css->get_child_target( $file ), 'write' ) ):
            $mode = 'direct' == $this->fs_method ? FALSE : 0666;
            $file = $this->fspath( $file );
            if ( $wp_filesystem->exists( $file ) ):
                $this->debug( 'File exists, returning.', __FUNCTION__ );
                return FALSE;
            else:
                $this->debug( 'Writing to filesystem: ' . $file . LF . $contents, __FUNCTION__ );
                if ( FALSE === $wp_filesystem->put_contents( 
                    $file, 
                    $contents,
                    $mode 
                    ) ):
                    $this->debug( 'Filesystem write failed, returning.', __FUNCTION__ );
                    return FALSE; 
                endif;
            endif;
        else:
            $this->debug( 'No directory, returning.', __FUNCTION__ );
            return FALSE;
        endif;
        $this->debug( 'Filesystem write successful.', __FUNCTION__ );
    }
    
    function copy_screenshot( $obj ) {
        // always copy screenshot
        $this->copy_parent_file( 'screenshot' ); 
    }
    
    function copy_parent_file( $file, $ext = 'php' ) {
        if ( !$this->fs ): 
            $this->debug( 'No filesystem access.', __FUNCTION__ );
            return FALSE; // return if no filesystem access
        endif;
        global $wp_filesystem;
        $parent_file = NULL;
        if ( 'screenshot' == $file ):
            foreach ( array_keys( $this->imgmimes ) as $extreg ): 
                foreach( explode( '|', $extreg ) as $ext ):
                    if ( $parent_file = $this->css->is_file_ok( $this->css->get_parent_source( 'screenshot.' . $ext ) ) ) break;
                endforeach; 
                if ( $parent_file ):
                    $parent_file = $this->fspath( $parent_file );
                    break;
                endif;
            endforeach;
        else:
            $parent_file = $this->fspath( $this->css->is_file_ok( $this->css->get_parent_source( $file . '.' . $ext ) ) );
        endif;
        // get child theme + file + ext ( passing empty string and full child path to theme_basename )
        $child_file = $this->css->get_child_target( $file . '.' . $ext );
        // return true if file already exists
        if ( $wp_filesystem->exists( $this->fspath( $child_file ) ) ) return TRUE;
        $child_dir = dirname( $this->theme_basename( '', $child_file ) );
        $this->debug( 'Verifying child dir... ', __FUNCTION__ );
        if ( $parent_file // sanity check
            && $child_file // sanity check
                && $this->verify_child_dir( $child_dir ) //create child subdir if necessary
                    && $wp_filesystem->copy( $parent_file, $this->fspath( $child_file ), FS_CHMOD_FILE ) ):
            $this->debug( 'Filesystem copy successful', __FUNCTION__ );
            return TRUE;
        endif;
        $this->errors[] = __( 'Could not copy file:' . $parent_file, 'child-theme-configurator' );
    }
    
    function delete_child_file( $file, $ext = 'php' ) {
        if ( !$this->fs ): 
            $this->debug( 'No filesystem access.', __FUNCTION__ );
            return FALSE; // return if no filesystem access
        endif;
        global $wp_filesystem;
        // verify file is in child theme and exists before removing.
        $file = ( 'img' == $ext ? $file : $file . '.' . $ext );
        if ( $child_file  = $this->css->is_file_ok( $this->css->get_child_target( $file ), 'write' ) ):
            if ( $wp_filesystem->exists( $this->fspath( $child_file ) ) ):
                if ( $wp_filesystem->delete( $this->fspath( $child_file ) ) ):
                    return TRUE;
                else:
                    $this->errors[] = __( 'Could not delete ' . $ext . ' file.', 'child-theme-configurator' );
                    $this->debug( 'Could not delete ' . $ext . ' file', __FUNCTION__ );
                endif;
            endif;
        endif;
    }
    
    function get_files( $theme, $type = 'template' ) {
        //$this->debug( LF . LF, __FUNCTION__ );
        $isparent = ( $theme === $this->get( 'parnt' ) );
        if ( ( $templates = $this->get( 'templates' ) ) && $isparent && 'template' == $type ): 
            return $templates;
        elseif ( !isset( $this->files[ $theme ] ) ):

            $this->files[ $theme ] = array();
            $imgext = '(' . implode( '|', array_keys( $this->imgmimes ) ) . ')';
            foreach ( $this->css->recurse_directory(
                trailingslashit( get_theme_root() ) . $theme, '', TRUE ) as $file ):
                $file = $this->theme_basename( $theme, $file );
                if ( preg_match( "/^style\-(\d+)\.css$/", $file, $matches ) ):
                    $date = date_i18n( 'D, j M Y g:i A', strtotime( $matches[ 1 ] ) );
                    $this->files[ $theme ][ 'backup' ][ $file ] = $date;
                    //$this->debug( 'This is a backup file', __FUNCTION__ );
                elseif ( preg_match( "/^ctc\-plugins\-(\d+)\.css$/", $file, $matches ) ):
                    $date = date_i18n( 'D, j M Y g:i A', strtotime( $matches[ 1 ] ) );
                    $this->files[ $theme ][ 'pluginbackup' ][ $file ] = $date;
                    //$this->debug( 'This is a plugin backup file', __FUNCTION__ );
                elseif ( preg_match( "/\.php$/", $file ) ):
                    if ( $isparent ):
                    
                        if ( ( $file_verified = $this->css->is_file_ok( $this->css->get_parent_source( $file, $theme ) , 'read' ) ) ):
                            $this->debug( 'scanning ' . $file_verified . '... ', __FUNCTION__ );
                            // read 2k at a time and bail if code detected
                            $template = FALSE;
                            if ( $handle = fopen( $file_verified, "rb") ):
                            while ( !feof( $handle ) ) {
                                $contents = fread($handle, 2048);
                                if ( preg_match( "/\w+\s*\(/", $contents ) ):
                                    $template = TRUE;
                                    if ( preg_match( "/(function \w+?|require(_once)?)\s*\(/", $contents ) ):
                                        $template = FALSE;
                                        break;
                                    endif;
                                endif;
                            }
                            fclose( $handle );
                            endif;
                            if ( $template )
                                $this->files[ $theme ][ 'template' ][] = $file;
                        endif;
                    else:
                        //$this->debug( 'Child PHP, adding to templates', __FUNCTION__ );
                        $this->files[ $theme ][ 'template' ][] = $file;
                    endif;
                elseif ( preg_match( "/\.css$/", $file ) && 'style.css' != $file ):
                    $this->files[ $theme ][ 'stylesheet' ][] = $file;
                    //$this->debug( 'This is a stylesheet', __FUNCTION__ );
                elseif ( preg_match( "/^images\/.+?\." . $imgext . "$/", $file ) ):
                    $this->files[ $theme ][ 'img' ][] = $file;
                    //$this->debug( 'This is an image file', __FUNCTION__ );
                endif;
            endforeach;
        endif;
        if ( $isparent ):
            //$this->debug( 'Setting CSS object templates parameter', __FUNCTION__ );
            $this->css->templates = $this->files[ $theme ][ 'template' ];
        endif;
        $types = explode(",", $type);
        $files = array();
        foreach ( $types as $type )
            if ( isset( $this->files[ $theme ][ $type ] ) )
                $files = array_merge( $this->files[ $theme ][ $type ], $files );
        return $files;
    }
        
    function theme_basename( $theme, $file ) {
        $file = $this->normalize_path( $file );
        // if no theme passed, returns theme + file
        $themedir = trailingslashit( $this->normalize_path( get_theme_root() ) ) . ( '' == $theme ? '' : trailingslashit( $theme ) );
        //$this->debug( 'Themedir: ' . $themedir . ' File: ' . $file , __FUNCTION__ );
        return preg_replace( '%^' . preg_quote( $themedir ) . '%', '', $file );
    }
    
    function uploads_basename( $file ) {
        $file = $this->normalize_path( $file );
        $uplarr = wp_upload_dir();
        $upldir = trailingslashit( $this->normalize_path( $uplarr[ 'basedir' ] ) );
        return preg_replace( '%^' . preg_quote( $upldir ) . '%', '', $file );
    }
    
    function uploads_fullpath( $file ) {
        $file = $this->normalize_path( $file );
        $uplarr = wp_upload_dir();
        $upldir = trailingslashit( $this->normalize_path( $uplarr[ 'basedir' ] ) );
        return $upldir . $file;
    }
    
    function serialize_postarrays() {
        foreach ( $this->postarrays as $field )
            if ( isset( $_POST[ $field ] ) && is_array( $_POST[ $field ] ) )
                $_POST[ $field ] = implode( "%%", $_POST[ $field ] );
    }
    
    function unserialize_postarrays() {
        foreach ( $this->postarrays as $field )
            if ( isset( $_POST[ $field ] ) && !is_array( $_POST[ $field ] ) )
                $_POST[ $field ] = explode( "%%", $_POST[ $field ] );
    }
    
    function set_writable( $file = NULL ) {

        if ( isset( $file ) ):
            $file =  $this->css->get_child_target( $file . '.php' );
        else:
            $file =  apply_filters( 'chld_thm_cfg_target', $this->css->get_child_target( 'separate' == $this->get( 'handling' ) ? 'ctc-style.css' : 'style.css' ), $this->css );
        endif;
        if ( $this->fs ): // filesystem access
            if ( is_writable( $file ) ) return;
            global $wp_filesystem;
            if ( $file && $wp_filesystem->chmod( $this->fspath( $file ), 0666 ) ) return;
        endif;
        $this->errors[] = __( 'Could not set write permissions.', 'child-theme-configurator' );
        add_action( 'admin_notices', array( $this, 'writable_notice' ) );     
        return FALSE;
    }
    
    function clone_child_theme( $child, $clone ) {
        if ( !$this->fs ) return FALSE; // return if no filesystem access
        global $wp_filesystem;
        // set child theme if not set for get_child_target to use new child theme as source
        $this->css->set_prop( 'child', $child );

        $dir        = untrailingslashit( $this->css->get_child_target( '' ) );
        $themedir   = trailingslashit( get_theme_root() );
        $fsthemedir = $this->fspath( $themedir );
        $files = $this->css->recurse_directory( $dir, NULL, TRUE );
        $errors = array();
        foreach ( $files as $file ):
            $childfile  = $this->theme_basename( $child, $this->normalize_path( $file ) );
            $newfile    = trailingslashit( $clone ) . $childfile;
            $childpath  = $fsthemedir . trailingslashit( $child ) . $childfile;
            $newpath    = $fsthemedir . $newfile;
            $this->debug( 'Verifying child dir... ', __FUNCTION__ );
            if ( $this->verify_child_dir( is_dir( $file ) ? $newfile : dirname( $newfile ) ) ):
                if ( is_file( $file ) && !@$wp_filesystem->copy( $childpath, $newpath ) ):
                    $errors[] = 'could not copy ' . $newpath;
                endif;
            else:
                $errors[] = 'invalid dir: ' . $newfile;
            endif;
        endforeach;
    }

    function unset_writable() {
        if ( !$this->fs ) return FALSE; // return if no filesystem access
        global $wp_filesystem;
        $dir        = untrailingslashit( $this->css->get_child_target( '' ) );
        $child      = $this->theme_basename( '', $dir );
        $newchild   = untrailingslashit( $child ) . '-new';
        $themedir   = trailingslashit( get_theme_root() );
        $fsthemedir = $this->fspath( $themedir );
        // is child theme owned by user? 
        if ( fileowner( $dir ) == fileowner( $themedir ) ):
            $copy   = FALSE;
            $wp_filesystem->chmod( $dir );
            // recursive chmod ( as user )
            // WP_Filesystem RECURSIVE CHMOD IS FLAWED! IT SETS ALL CHILDREN TO PERM OF OUTERMOST DIR
            //if ( $wp_filesystem->chmod( $this->fspath( $dir ), FALSE, TRUE ) ):
            //endif;
        else:
            $copy   = TRUE;
        endif;
        // n -> copy entire folder ( as user )
        $files = $this->css->recurse_directory( $dir, NULL, TRUE );
        $errors = array();
        foreach ( $files as $file ):
            $childfile  = $this->theme_basename( $child, $this->normalize_path( $file ) );
            $newfile    = trailingslashit( $newchild ) . $childfile;
            $childpath  = $fsthemedir . trailingslashit( $child ) . $childfile;
            $newpath    = $fsthemedir . $newfile;
            if ( $copy ):
                $this->debug( 'Verifying child dir... ' . $file, __FUNCTION__ );
                if ( $this->verify_child_dir( is_dir( $file ) ? $newfile : dirname( $newfile ) ) ):
                    if ( is_file( $file ) && !$wp_filesystem->copy( $childpath, $newpath ) ):
                        $errors[] = 'could not copy ' . $newpath;
                    endif;
                else:
                    $errors[] = 'invalid dir: ' . $newfile;
                endif;
            else:
                $wp_filesystem->chmod( $this->fspath( $file ) );
            endif;
        endforeach;
        if ( $copy ):
            // verify copy ( as webserver )
            $newfiles = $this->css->recurse_directory( trailingslashit( $themedir ) . $newchild, NULL, TRUE );
            $deleteddirs = $deletedfiles = 0;
            if ( count( $newfiles ) == count( $files ) ):
                // rename old ( as webserver )
                if ( !$wp_filesystem->exists( trailingslashit( $fsthemedir ) . $child . '-old' ) )
                    $wp_filesystem->move( trailingslashit( $fsthemedir ) . $child, trailingslashit( $fsthemedir ) . $child . '-old' );
                // rename new ( as user )
                if ( !$wp_filesystem->exists( trailingslashit( $fsthemedir ) . $child ) )
                    $wp_filesystem->move( trailingslashit( $fsthemedir ) . $newchild, trailingslashit( $fsthemedir ) . $child );
                // remove old files ( as webserver )
                $oldfiles = $this->css->recurse_directory( trailingslashit( $themedir ) . $child . '-old', NULL, TRUE );
                array_unshift( $oldfiles, trailingslashit( $themedir ) . $child . '-old' );
                foreach ( array_reverse( $oldfiles ) as $file ):
                    if ( $wp_filesystem->delete( $this->fspath( $file ) ) 
                        || ( is_dir( $file ) && @rmdir( $file ) ) 
                            || ( is_file( $file ) && @unlink( $file ) ) ):
                        $deletedfiles++;
                    endif;
                endforeach;
                if ( $deletedfiles != count( $oldfiles ) ):
                    $errors[] = 'deleted: ' . $deletedfiles . ' != ' . count( $oldfiles ) . ' files';
                endif;
            else:
                $errors[] = 'newfiles != files';
            endif;
        endif;
        if ( count( $errors ) ):
            $this->errors[] = __( 'There were errors while resetting permissions.', 'child-theme-configurator' ) ;
            add_action( 'admin_notices', array( $this, 'writable_notice' ) );     
        endif;
    }
    
    function set_skip_form() {
        $this->skip_form = TRUE;
    }
    
    function handle_file_upload( $field, $childdir = NULL, $mimes = NULL ){
        $uploadedfile = $_FILES[ $field ];
        $upload_overrides = array( 
            'test_form' => FALSE,
            'mimes' => ( is_array( $mimes ) ? $mimes : NULL )
        );
        if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
        if ( isset( $movefile[ 'error' ] ) ):
            $this->errors[] = $movefile[ 'error' ];
            return FALSE;
        endif;
        $_POST[ 'movefile' ] = $this->uploads_basename( $movefile[ 'file' ] );        
    }
    
    function move_file_upload( $subdir = 'images' ) {
        if ( !$this->fs ) return FALSE; // return if no filesystem access
        global $wp_filesystem;
        $source_file = sanitize_text_field( $_POST[ 'movefile' ] );
        $target_file = ( '' == $subdir ? 
            preg_replace( "%^.+(\.\w+)$%", "screenshot$1", basename( $source_file ) ) : 
                trailingslashit( $subdir ) . basename( $source_file ) );
        $this->debug( 'Verifying child dir... ', __FUNCTION__ );
        if ( FALSE !== $this->verify_child_dir( trailingslashit( $this->get( 'child' ) ) . $subdir ) ):
            $source_path = $this->fspath( $this->uploads_fullpath( $source_file ) );
            if ( $target_path = $this->css->is_file_ok( $this->css->get_child_target( $target_file ), 'write' ) ):
                $target_path = $this->fspath( $target_path );
                if ( $wp_filesystem->exists( $source_path ) ):
                    if ( $wp_filesystem->move( $source_path, $target_path ) ) return TRUE;
                endif;
            endif;
        endif;
        
        $this->errors[] = __( 'Could not upload file.', 'child-theme-configurator' );        
    }
    
    /**
     * exports theme as zip archive.
     * As of version 2.03, parent themes can be exported as well
     */
    function export_zip() {
        $version = '';
        if ( empty( $_POST[ 'ctc_export_theme' ] ) ):
            $template = $this->get( 'child' );
            $version = preg_replace( "%[^\w\.\-]%", '', $this->get( 'version' ) );
        else:
            $template = sanitize_text_field( $_POST[ 'ctc_export_theme' ] );
            if ( ( $theme = wp_get_theme( $template ) ) && is_object( $theme ) )
                $version = preg_replace( "%\.\d{10}$%", '', $theme->Version );
        endif;
        // make sure directory exists and is in themes folder
        if ( ( $dir = $this->css->is_file_ok( trailingslashit( get_theme_root() ) . $template, 'search' ) ) ):
            // Try to use php system upload dir to store temp files first
            $tmpdir = ini_get( 'upload_tmp_dir' ) ? ini_get( 'upload_tmp_dir' ) : sys_get_temp_dir();
            if ( !is_writable( $tmpdir ) ):
                // try uploads directory
                $uploads = wp_upload_dir();
                $tmpdir = $uploads[ 'basedir' ];
                if ( !is_writable( $tmpdir ) ):
                    $this->errors[] = __( 'No writable temp directory.', 'child-theme-configurator' );
                    return FALSE;
                endif;
            endif;
            $file = trailingslashit( $tmpdir ) . $template . ( empty( $version ) ? '' : '-' . $version ) . '.zip';
            if ( file_exists( $file ) ) unlink ( $file );

            mbstring_binary_safe_encoding();
            
            // PclZip ships with WordPress
            require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );

            $archive = new PclZip( $file );
            if ( $response = $archive->create( $dir, PCLZIP_OPT_REMOVE_PATH, dirname( $dir ) ) ):
    
                reset_mbstring_encoding();
                header( 'Content-Description: File Transfer' );
                header( 'Content-Type: application/octet-stream' );
                header( 'Content-Length: ' . filesize( $file ) );
                header( 'Content-Disposition: attachment; filename=' . basename( $file ) );
                header( 'Expires: 0' );
                header( 'Cache-Control: must-revalidate' );
                header( 'Pragma: public' );
                readfile( $file );
                unlink( $file );
                die();
            else:
                $this->errors[] = __( 'PclZip returned zero bytes.', 'child-theme-configurator' );
            endif;
        else:
            $this->errors = __( 'Invalid theme root directory.', 'child-theme-configurator' );
        endif;

    }
        
    /*
     *
     */
    function verify_creds( $args = array() ) {
        $this->fs_prompt = $this->fs = FALSE;
        //fs prompt does not support arrays as post data - serialize arrays
        $this->serialize_postarrays();
        // generate callback url
        $ctcpage = apply_filters( 'chld_thm_cfg_admin_page', CHLD_THM_CFG_MENU );
        $url = is_multisite() ?  network_admin_url( 'themes.php?page=' . $ctcpage ) :
            admin_url( 'tools.php?page=' . $ctcpage );
        $nonce_url = wp_nonce_url( $url, apply_filters( 'chld_thm_cfg_action', 'ctc_update' ), '_wpnonce' );
        // buffer output so we can process prior to http header
        ob_start();
        if ( $creds = request_filesystem_credentials( $nonce_url, '', FALSE, FALSE, $args ) ):
            // check filesystem permission if direct or ftp creds exist
            if ( WP_Filesystem( $creds ) )
                // login ok
                $this->fs = TRUE;
            else
                // incorrect credentials, get form with error flag
                $creds = request_filesystem_credentials( $nonce_url, '', TRUE, FALSE, $args );
        else:
            // no credentials, initialize unpriveledged filesystem object
            WP_Filesystem();
        endif;
        // if form was generated, store it
        $this->fs_prompt = ob_get_contents();
        // now we can read/write if fs is TRUE otherwise fs_prompt will contain form
        ob_end_clean();
         //fs prompt does not support arrays as post data - unserialize arrays
        $this->unserialize_postarrays();
   }
    
    /*
     * convert 'direct' filepath into wp_filesystem filepath
     */
    function fspath( $file ){
        if ( ! $this->fs ) return FALSE; // return if no filesystem access
        global $wp_filesystem;
        if ( is_dir( $file ) ):
            $dir = $file;
            $base = '';
        else:
            $dir = dirname( $file );
            $base = basename( $file );
        endif;
        $fsdir = $wp_filesystem->find_folder( $dir );
        return trailingslashit( $fsdir ) . $base;
    }
    
    // back compatibility function for legacy plugins extension
    function write_addl_files( $obj ) {
        global $chld_thm_cfg_plugins;
        if ( !is_object( $chld_thm_cfg_plugins ) || !$this->fs ) return FALSE;
        $configtype = $this->get( 'configtype' );
        //echo $configtype . LF;
        if ( 'theme' == $configtype || !( $def = $chld_thm_cfg_plugins->defs->get_def( $configtype ) ) ) return FALSE;
        $child = trailingslashit( $this->get( 'child' ) );
        if ( isset( $def[ 'addl' ] ) && is_array( $def[ 'addl' ] ) && count( $def[ 'addl' ] ) ):
            foreach ( $def[ 'addl' ] as $path => $type ):
            
                // sanitize the crap out of the target data -- it will be used to create paths
                $path = $this->normalize_path( preg_replace( "%[^\w\\//\-]%", '', sanitize_text_field( $child . $path ) ) );
                $this->debug( 'Verifying child dir... ', __FUNCTION__ );
                if ( ( 'dir' == $type && FALSE === $this->verify_child_dir( $path ) )
                    || ( 'dir' != $type && FALSE === $this->write_child_file( $path, '' ) ) ):
                    //$this->errors[] = __( 'Your theme directories are not writable.', 'chld_thm_cfg_plugins' );
                endif;
            endforeach;
        endif;
        // write main def file
        if ( isset( $def[ 'target' ] ) ):
            $path = $this->normalize_path( preg_replace( "%[^\w\\//\-\.]%", '', sanitize_text_field( $def[ 'target' ] ) ) ); //$child . 
            if ( FALSE === $this->write_child_file( $path, '' ) ):
                //echo "invalid path: " . $path . ' ' . ' was: ' . $def[ 'target' ] . LF;
                //$this->errors[] = __( 'Your stylesheet is not writable.', 'chld_thm_cfg_plugins' );
                return FALSE;
            endif;
        endif;        
    }
    
    // backwards compatability < WP 3.9
    function normalize_path( $path ) {
        $path = str_replace( '\\', '/', $path );
        // accommodates windows NT paths without C: prefix
        $path = substr( $path, 0, 1 ) . preg_replace( '|/+|','/', substr( $path, 1 ) );
        if ( ':' === substr( $path, 1, 1 ) )
            $path = ucfirst( $path );
        return $path;
    }

    // case insensitive theme search    
    function check_theme_exists( $theme ) {
        $search_array = array_map( 'strtolower', array_keys( wp_get_themes() ) );
        return in_array( strtolower( $theme ), $search_array );
    }
    
    // helper functions to support legacy plugin extension
    function is_legacy() {
        return defined('CHLD_THM_CFG_PLUGINS_VERSION') 
            && version_compare( CHLD_THM_CFG_PLUGINS_VERSION, '2.0.0', '<' );
    }
    
    /* not using plugin mode */
    function is_theme( $configtype = '' ) {
        // if filter returns a value, we are using plugin mode
        // otherwise if configtype has a value and it is not a theme then we are in legacy plugin mode
        $pluginmode = apply_filters( 'chld_thm_cfg_action', NULL );
        if ( $pluginmode || ( !empty( $configtype ) && 'theme' != $configtype ) ):
            return FALSE;
        endif;
        if ( $this->is_legacy()
            && is_object( $this->css ) 
                && ( $configtype = $this->get( 'configtype' ) ) 
                    && !empty( $configtype ) && 'theme' != $configtype ):
            return FALSE;
        endif;
        return TRUE;
    }
    
    /* returns parent theme either from existing config or passed as post var */
    function get_current_parent() {
        // check if child was passed and use Template value
        if ( isset( $_GET[ 'ctc_child' ] ) && ( $child = sanitize_text_field( $_GET[ 'ctc_child' ] ) ) )
            return $this->themes[ 'child' ][ $child ][ 'Template' ];
        // otherwise check if parent was passed
        if ( isset( $_GET[ 'ctc_parent' ] ) && ( $parent = sanitize_text_field( $_GET[ 'ctc_parent' ] ) ) )
            return $parent;
        // otherwise use css object value
        elseif ( $parent = $this->get( 'parnt' ) )
            return $parent;
        // otherwise use template value
        else return get_template();
    }
    
    /* returns child theme either from existing config or passed as post var */
    function get_current_child() {
        // check if parent was passed
        if ( isset( $_GET[ 'ctc_child' ] ) && ( $child = sanitize_text_field( $_GET[ 'ctc_child' ] ) ) )
            return $child;
        // otherwise use css object value
        elseif ( $child = $this->get( 'child' ) )
            return $child;
        // otherwise use stylesheet value
        else return get_stylesheet();
    }
        
    function toggle_debug() {
        $debug = '';
        if ( $_POST[ 'ctc_is_debug' ] ):
            $this->is_debug = 1;
            $debug = $this->print_debug( TRUE );
        else:
            $this->is_debug = 0;
        endif;
        update_option( CHLD_THM_CFG_OPTIONS . '_debug', $this->is_debug );
        $this->updates[] = array(
            'obj'   => 'debug',
            'key'   => '',
            'data'  => $debug,
        );
    }
    
    function debug( $msg = NULL, $fn = NULL) {
        $this->debug .= ( isset( $fn ) ? $fn . ': ' : '' ) . ( isset( $msg ) ? $msg . LF : '' );
    }
    
    function print_debug( $noecho = FALSE ) {
        $this->debug( '*** END OF REQUEST ***', __FUNCTION__ );
        // save debug data for 1 hour
        set_site_transient( CHLD_THM_CFG_OPTIONS . '_debug', $this->debug, 3600 );
        $debug = '<textarea style="width:100%;height:200px">' . LF . $this->debug . LF . '</textarea>' . LF;
        if ( $noecho ) return $debug;
        echo $debug;
    }
    
    function parse_parent_stylesheet_to_source() {
        $this->css->parse_css_file( 'parnt' );
    }
    
    function parse_child_stylesheet_to_source() {
        $this->css->parse_css_file( 'child', 'style.css', 'parnt' );
    }
    
    function parse_child_stylesheet_to_target() {
        $this->css->parse_css_file( 'child', 'style.css' );
    }
    
    function parse_custom_stylesheet_to_target() {
        $this->css->parse_css_file( 'child', 'ctc-style.css' );
    }
        
    function parse_genesis_stylesheet_to_source() {
        $this->css->parse_css_file( 'child', 'ctc-genesis.css', 'parnt' );
    }
        
    function parse_additional_stylesheets_to_source() {
        // parse additional stylesheets
            foreach ( $this->css->addl_css as $file ):
                //$file = sanitize_text_field( $file );
                $this->css->parse_css_file( 'parnt', $file );
            endforeach;
            $this->debug( print_r( $this->css->addl_css, TRUE ), __FUNCTION__ );
        //endif;
    }
    
    function reset_child_theme() {
        $parnt  = $this->get( 'parnt' );
        $child  = $this->get( 'child' );
        $name   = $this->get( 'child_name' );
        $this->css = new ChildThemeConfiguratorCSS();
        $this->css->set_prop( 'parnt', $parnt );
        $this->css->set_prop( 'child', $child );
        $this->css->set_prop( 'child_name', $name );
        $this->css->set_prop( 'enqueue', 'enqueue' );
        $this->backup_or_restore_file( 'header.php', TRUE );
        $this->delete_child_file( 'header.ctcbackup', 'php' );
        $this->backup_or_restore_file( 'style.css', TRUE );
        $this->delete_child_file( 'style.ctcbackup', 'css' );
        $this->backup_or_restore_file( 'ctc-style.css', TRUE );
        $this->delete_child_file( 'ctc-style.ctcbackup', 'css' );
    }
    
    function copy_theme_mods( $from, $to ) {
        
        // we can copy settings from parent to child even if neither is currently active
        // so we need cases for active parent, active child or neither
        
        // get active theme
        $active_theme = get_stylesheet();
        $this->debug( 'from: ' . $from . ' to: ' . $to . ' active: ' . $active_theme, __FUNCTION__ );
        // create temp array from parent settings
        $child_mods = get_option( 'theme_mods_' . $from );
        if ( $active_theme == $from ):
            $this->debug( 'from is active, using active widgets', __FUNCTION__ );
            // if parent theme is active, get widgets from active sidebars_widgets array
            $child_widgets = retrieve_widgets();
        else:
            $this->debug( 'from not active, using theme mods widgets', __FUNCTION__ );
            // otherwise get widgets from parent theme mods
            $child_widgets = empty( $child_mods[ 'sidebars_widgets' ][ 'data' ] ) ?
                array( 'wp_inactive_widgets' => array() ) : $child_mods[ 'sidebars_widgets' ][ 'data' ];
        endif;
        if ( $active_theme == $to ):
            $this->debug( 'to active, setting active widgets', __FUNCTION__ );
            // if child theme is active, remove widgets from temp array
            unset( $child_mods[ 'sidebars_widgets' ] );
            // copy widgets to active sidebars_widgets array
            wp_set_sidebars_widgets( $child_widgets );
        else:
            $this->debug( 'child not active, saving widgets in theme mods', __FUNCTION__ );
            // otherwise copy widgets to temp array with time stamp
            $child_mods[ 'sidebars_widgets' ][ 'data' ] = $child_widgets;
            $child_mods[ 'sidebars_widgets' ][ 'time' ] = time();
        endif;
        $this->debug( 'saving child theme mods:' . LF . print_r( $child_mods, TRUE ), __FUNCTION__ );
        // copy temp array to child mods
        update_option( 'theme_mods_' . $to, $child_mods );
    }
    
    function network_enable() {
        if ( $child = $this->get( 'child' ) ):
            $allowed_themes = get_site_option( 'allowedthemes' );
            $allowed_themes[ $child ] = true;
            update_site_option( 'allowedthemes', $allowed_themes );
        endif;
    }
    
    function backup_or_restore_file( $source, $restore = FALSE, $target = NULL ){
        $action = $restore ? 'Restore' : 'Backup';
        $this->debug( LF . LF . $action . ' main stylesheet...', __FUNCTION__ );
        if ( !$this->fs ): 
            $this->debug( 'No filesystem access, returning', __FUNCTION__ );
            return FALSE; // return if no filesystem access
        endif;
        list( $base, $suffix ) = explode( '.', $source );
        if ( empty( $target ) )
            $target = $base . '.ctcbackup.' . $suffix;
        if ( $restore ):
            $source = $target;
            $target = $base . '.' . $suffix;        
        endif;
        $fstarget = $this->fspath( $this->css->get_child_target( $target ) );
        $fssource = $this->fspath( $this->css->get_child_target( $source ) );
        global $wp_filesystem;
        if ( ( !$wp_filesystem->exists( $fssource ) ) || ( !$restore && $wp_filesystem->exists( $fstarget ) ) ):
            $this->debug( 'No stylesheet, returning', __FUNCTION__ );
            return FALSE;
        endif;
        if ( $wp_filesystem->copy( $fssource, $fstarget, FS_CHMOD_FILE ) ):
            $this->debug( 'Filesystem ' . $action . ' successful', __FUNCTION__ );
            return TRUE;
        else:
            $this->debug( 'Filesystem ' . $action . ' failed', __FUNCTION__ );
            return FALSE;
        endif;
    }
    
    function rewrite_stylesheet_header(){
        $this->debug( LF . LF . 'Rewriting main stylesheet header...', __FUNCTION__ );
        if ( !$this->fs ): 
            $this->debug( 'No filesystem access, returning', __FUNCTION__ );
            return FALSE; // return if no filesystem access
        endif;
        $origcss        = $this->css->get_child_target( 'style.css' );
        $fspath         = $this->fspath( $origcss );
        global $wp_filesystem;
        if( !$wp_filesystem->exists( $fspath ) ): 
            $this->debug( 'No stylesheet, returning', __FUNCTION__ );
            return FALSE;
        endif;
        // get_contents_array returns extra linefeeds so just split it ourself
        $contents       = $wp_filesystem->get_contents( $fspath );
        $child_headers  = $this->css->get_css_header();
        if ( is_array( $child_headers ) )
            $regex      = implode( '|', array_map( 'preg_quote', array_keys( $child_headers ) ) );
        else $regex     = 'NO HEADERS';
        $regex          = '/(' . $regex . '):.*$/';
        $this->debug( 'regex: ' . $regex, __FUNCTION__ );
        $header         = str_replace( "\r", LF, substr( $contents, 0, 8192 ) );
        $contents       = substr( $contents, 8192 );
        $this->debug( 'original header: ' . LF . $header, __FUNCTION__ );
        //$this->debug( 'stripping @import rules...', __FUNCTION__ );
        // strip out existing @import lines
        $header = preg_replace( '#\@import\s+url\(.+?\);\s*#s', '', $header );
        // parse header line by line
        $headerdata     = explode( "\n", $header );
        $in_comment     = 0;
        $found_header   = 0;
        $headerdone     = 0;
        $newheader      = '';
        if ( $headerdata ):
            $this->debug( 'parsing header...', __FUNCTION__ );
            foreach ( $headerdata as $n => $headerline ):
                preg_match_all("/(\*\/|\/\*)/", $headerline, $matches );
                if ( $matches ):
                    foreach ( $matches[1] as $token ): 
                        if ( '/*' == $token ):
                            $in_comment = 1;
                        elseif ( '*/' == $token ):
                            $in_comment = 0;
                        endif;
                    endforeach;
                endif;
                if ( $in_comment ):
                    $this->debug( 'in comment', __FUNCTION__ );
                    if ( preg_match( $regex, $headerline, $matches ) && !empty( $matches[ 1 ] ) ):
                        $found_header = 1;
                        $key = $matches[ 1 ];
                        $this->debug( 'found header: ' . $key, __FUNCTION__ );
                        if ( array_key_exists( $key, $child_headers ) ):
                            $this->debug( 'child header value exists: ', __FUNCTION__ );
                            $value = trim( $child_headers[ $key ] );
                            unset( $child_headers[ $key ] );
                            if ( $value ):
                                $this->debug( 'setting ' . $key . ' to ' . $value, __FUNCTION__ );
                                $count = 0;
                                $headerline = preg_replace( 
                                    $regex, 
                                    ( empty( $value ) ? '' : $key . ': ' . $value ), 
                                    $headerline
                                );
                            else:
                                $this->debug( 'removing ' . $key, __FUNCTION__ );
                                continue;
                            endif;
                        endif;
                    endif;
                    $newheader .= $headerline . LF;
                elseif ( $found_header && !$headerdone ): // we have gone in and out of header block; insert any remaining parameters
                    $this->debug( 'not in comment and after header', __FUNCTION__ );
                    foreach ( $child_headers as $key => $value ):
                        $this->debug( 'inserting ' . $key . ': ' . $value, __FUNCTION__ );
                        if ( empty( $value ) ) continue;
                        $newheader .= $key . ': ' . trim( $value ) . "\n";
                    endforeach;
                    // if importing parent, add after this line
                    $newheader .= $headerline . "\n" . $this->css->get_css_imports();
                    $headerdone = 1;
                else:
                    $this->debug( 'not in comment', __FUNCTION__ );
                    $newheader .= $headerline . LF;
                endif;
            endforeach;
            $this->debug( 'new header: ' . LF . $newheader, __FUNCTION__ );
            if ( !$found_header ) return FALSE;
        endif;
        $contents = $newheader . $contents;
        if ( FALSE === $wp_filesystem->put_contents( $fspath, $contents ) ):
            //$this->debug( 'Filesystem write to ' . $fspath . ' failed.', __FUNCTION__ );
        else:
            //$this->debug( 'Filesystem write to ' . $fspath . ' successful.', __FUNCTION__ );
        endif;
        //die( '<pre><code>' . $contents . '</code></pre>');
    }

    function max_styles_notice() {
        $this->ui->notices( 'max_styles' );
    }

    function config_notice() {
        $this->ui->notices( 'config' );
    }
    
    function writable_notice() {
        $this->ui->notices( 'writable' );
    }
    
    function enqueue_notice() {
        $this->ui->notices( 'enqueue' );
    }
    
    function owner_notice() {
        $this->ui->notices( 'owner' );
    }
    
    function changed_notice() {
        $this->ui->notices( 'changed' );
    }
    
    function upgrade_notice() {
        $this->ui->notices( 'upgrade' );
    }
    
    function get_child_stylesheet() {
        $handling = $this->get( 'handling' );
        if ( 'separate' == $handling )
            return 'ctc-style.css';
        elseif ( 'reset' == $this->childtype )
            return FALSE;
        else
            return 'style.css';
    }
    /**
     * for themes with hard-coded stylesheets,
     * change references to stylesheet_uri to template_directory_uri  
     * and move wp_head to end of head if possible
     */
    function repair_header() {
        // return if no flaws detected
        if ( ! $this->get( 'cssunreg' ) && !$this->get( 'csswphead' ) ) return;
        $this->debug( 'repairing parent header', __FUNCTION__ );
        // try to copy from parent
        $this->copy_parent_file( 'header' );
        // try to backup child header template
        $this->backup_or_restore_file( 'header.php' );
        // fetch current header template
        global $wp_filesystem;
        $cssstr = "get_template_directory_uri()";
        $wphstr = '<?php // MODIFIED BY CTC' . LF . 'wp_head();' . LF . '?>' . LF . '</head>';
        $filename = $this->css->get_child_target( 'header.php' );
        $contents = $wp_filesystem->get_contents( $this->fspath( $filename ) );
        
        // change hard-wired stylesheet link so it loads parent theme instead
        if ( $this->get( 'cssunreg' ) || $this->get( 'csswphead' ) ):
            $repairs = 0;
            $contents = preg_replace( "#(get_bloginfo\(\s*['\"]stylesheet_url['\"]\s*\)|get_stylesheet_uri\(\s*\))#s", $cssstr . ' . "/style.css"', $contents, -1, $count ); 
            $repairs += $count;
            $contents = preg_replace( "#([^_])bloginfo\(\s*['\"]stylesheet_url['\"]\s*\)#s", "$1echo " . $cssstr . ' . "/style.css"', $contents, -1, $count );
            $repairs += $count;
            $contents = preg_replace( "#([^_])bloginfo\(\s*['\"]stylesheet_directory['\"]\s*\)#s", "$1echo " . $cssstr, $contents, -1, $count );
            $repairs += $count;
            $contents = preg_replace( "#(trailingslashit\()?(\s*)get_stylesheet_directory_uri\(\s*\)(\s*\))?\s*\.\s*['\"]\/?([\w\-\.\/]+?)\.css['\"]#s", 
                "$2echo $cssstr . '$3.css'", $contents, -1, $count );
            $repairs += $count;
            if ( $repairs )
                $this->css->set_prop( 'parntloaded', TRUE );
        endif;

        // put wp_head() call at the end of <head> section where it belongs
        if ( $this->get( 'csswphead' ) ):
            $contents = preg_replace( "#wp_head\(\s*\)\s*;#s", '', $contents );
            $contents = preg_replace( "#</head>#s", $wphstr, $contents );
            $contents = preg_replace( "#\s*<\?php\s*\?>\s*#s", LF, $contents ); // clean up
        endif;
        
        // write new header template to child theme
        $this->debug( 'Writing to filesystem: ' . $filename . LF . $contents, __FUNCTION__ );
        if ( FALSE === $wp_filesystem->put_contents( $this->fspath( $filename ), $contents ) ):
            $this->debug( 'Filesystem write failed, returning.', __FUNCTION__ );
            return FALSE; 
        endif;
        //die( '<textarea>' . $contents . '</textarea>' );
    }
    
    /**
     * Evaluate signals collected from theme preview and set configuration accordingly
     */
    function evaluate_signals() {
        if ( !isset( $_POST[ 'ctc_analysis' ] ) ) return;
        $analysis   = json_decode( urldecode( $_POST[ 'ctc_analysis' ] ) );
        //die( print_r( $analysis, TRUE ) );
        // stylesheets loaded outside wp_styles queue
        $unregs     = array( 'thm_past_wphead', 'thm_unregistered', 'dep_unregistered', 'css_past_wphead', 'dep_past_wphead' );
        //echo '<pre><code>' . print_r( $analysis, TRUE ) . "</code></pre>\n";
        
        // if this is a self-contained child theme ( e.g., Genesis ) use child as baseline
        $baseline = $this->get( 'ignoreparnt' ) ? 'child' : 'parnt';
        $this->debug( 'baseline: ' . $baseline, __FUNCTION__ );
        $this->css->parnt_deps  = array();
        $this->css->child_deps  = array();
        $this->css->addl_css    = array();
        
        // store stylesheet dependencies
        if ( isset( $analysis->{ $baseline } ) ):
            if ( isset( $analysis->{ $baseline }->deps ) ):
                foreach ( $analysis->{ $baseline }->deps[ 0 ] as $deparray ):
                    if ( !in_array( $deparray[ 0 ], $unregs ) ):
                        $this->css->parnt_deps[] = $deparray[ 0 ];
                    endif;
                    if ( !preg_match( "/^style([\-\.]min)?\.css$/", $deparray[ 1 ] ) ):
                        // bootstrap wastes memory among other resources
                        //if ( !preg_match( "/bootstrap/i", $deparray[ 0 ] ) && !preg_match( "/bootstrap/i", $deparray[ 1 ] ) )
                            $this->css->addl_css[] = sanitize_text_field( $deparray[ 1 ] );
                    endif;
                endforeach;
                foreach ( $analysis->{ $baseline }->deps[ 1 ] as $deparray ):
                    if ( !in_array( $deparray[ 0 ], $unregs ) ):
                        $this->css->child_deps[] = $deparray[ 0 ];
                    endif;
                    if ( 'separate' == $this->get( 'handling' ) || !empty( $analysis->{ $baseline }->signals->ctc_child_loaded ) ):
                        if ( !preg_match( "/^style([\-\.]min)?\.css$/", $deparray[ 1 ] ) ):
                            //if ( !preg_match( "/bootstrap/", $deparray[ 0 ] ) && !preg_match( "/bootstrap/", $deparray[ 1 ] ) )
                                $this->css->addl_css[] = sanitize_text_field( $deparray[ 1 ] );
                        endif;
                    endif;
                endforeach;
            endif;
        endif;
        // store parent theme signals
        if ( isset( $analysis->{ $baseline }->signals ) ):
            $this->css->set_prop( 'hasstyles', isset( $analysis->{ $baseline }->signals->thm_no_styles ) ? 0 : 1 );
            $this->css->set_prop( 'csswphead', isset( $analysis->{ $baseline }->signals->thm_past_wphead ) ? 1 : 0 );
            $this->css->set_prop( 'cssunreg', isset( $analysis->{ $baseline }->signals->thm_unregistered ) ? 1 : 0 );
            if ( isset( $analysis->{ $baseline }->signals->thm_parnt_loaded ) ):
                $this->set_enqueue_priority( $analysis, $baseline );
            endif;
        endif;
        if ( isset( $analysis->child->signals ) ):
            // test these again for child theme 
            $this->css->set_prop( 'csswphead', isset( $analysis->child->signals->thm_past_wphead ) ? 1 : 0 );
            $this->css->set_prop( 'cssunreg', isset( $analysis->child->signals->thm_unregistered ) ? 1 : 0 );
            // special case where theme does not link child stylesheet at all
            $this->css->set_prop( 'cssnotheme', isset( $analysis->child->signals->thm_notheme ) ? 1 : 0 );
            if ( isset( $analysis->child->signals->thm_child_loaded ) ):
                $this->css->set_prop( 'childloaded', $analysis->child->signals->thm_child_loaded );
                $this->set_enqueue_priority( $analysis, 'child' );
            else:
                $this->css->set_prop( 'childloaded',  0 );
            endif;
            // if theme loads parent theme when is_child_theme, add child dependency
            if ( isset( $analysis->child->signals->thm_parnt_loaded ) ):
                $this->css->set_prop( 'parntloaded',  $analysis->child->signals->thm_parnt_loaded );
                if ( 'thm_unregistered' != $analysis->child->signals->thm_parnt_loaded ):
                    array_unshift( $this->css->child_deps, $analysis->child->signals->thm_parnt_loaded );
                endif;
            else:
                $this->css->set_prop( 'parntloaded',  0 );
            endif;
            
            // if main styleheet is loading out of sequence, force dependency
            if ( isset( $analysis->child->signals->ctc_parnt_reorder ) )
                $this->reorder = TRUE;
            // roll back CTC Pro Genesis handling option
            if ( isset( $analysis->child->signals->ctc_gen_loaded ) )
                $this->genesis = TRUE;
        endif;
    }
    /**
     * Set the priority of the enqueue hook
     * by matching the hook handle of the primary stylesheet ( thm_parnt_loaded or thm_child_loaded )
     * to the hook handles that were passed by the preview fetched by the analyzer.
     * This allows the stylesheets to be enqueued in the correct order.
     */
    function set_enqueue_priority( $analysis, $baseline ){
        foreach ( $analysis->{ $baseline }->irreg as $irreg ):
            $handles = explode( ',', $irreg );
            $priority = array_shift( $handles );
            $handle = $analysis->{ $baseline }->signals->{ 'thm_' . $baseline . '_loaded' };
            if ( in_array( $handle, $handles ) ):
                $this->debug( '(baseline: ' . $baseline . ') match: ' . $handle . ' setting priority: ' . $priority, __FUNCTION__ );
                $this->css->set_prop( 'qpriority', $priority );
                break;
            endif;
        endforeach;
    }
}
