<?php /*
    Plugin Name: FeedBlitz FeedSmart
    Plugin URI: http://www.feedblitz.com
    Description: Modified by <a href="http://www.feedblitz.com">Phil Hollows</a> & <a href="http://www.commentluv.com">Andy Bailey</a>, derived from the FeedBurner FeedSmith plugin originlly written by <a href="http://www.orderedlist.com/">Steve Smith</a>, this plugin detects all ways to access your original WordPress feeds and redirects them to your FeedBlitz feed so you can track every possible subscriber. 
    Author: FeedBlitz & Andy Bailey
    Author URI: http://www.feedblitz.com
    Version: 1.2
    */

    if (! class_exists ( 'fb_feedsmart' )) {
        // let class begin
        class fb_feedsmart {
            //localization domain
            var $plugin_domain = 'fb_membermail_domain';
            var $plugin_url;
            var $plugin_dir;
            var $db_option = 'feedsmart_settings';
            var $version = '1.2';
            var $slug = 'feedsmart_settings-settings';

            /** fb_membermail
            * This is the constructor, it runs as soon as the class is created
            * Use this to set up hooks, filters, menus and language config
            */
            function __construct() {
                global $wp_version, $pagenow;
                // pages where this plugin needs translation
                $local_pages = array ('plugins.php', 'options-general.php' );
                // check if translation needed on current page
                if (in_array ( $pagenow, $local_pages ) || in_array ( $_GET ['page'], $local_pages )) {
                    $this->handle_load_domain ();
                }
                $exit_msg = __ ( 'Feedblitz feedsmart plugin requires Wordpress 2.9 or newer.', $this->plugin_domain ) . '<a href="http://codex.wordpress.org/Upgrading_Wordpress">' . __ ( 'Please Update!', $this->plugin_domain ) . '</a>';
                // can you dig it?
                if (version_compare ( $wp_version, "2.9", "<" )) {
                    echo ( $exit_msg ); // no diggedy
                }
                // plugin dir and url
                $this->plugin_url = trailingslashit ( WP_PLUGIN_URL . '/' . dirname ( plugin_basename ( __FILE__ ) ) );
                $this->plugin_dir = dirname(__FILE__);
                // hooks 
                if(is_admin()){
                    // filters
                    add_filter ( 'plugin_action_links', array (&$this, 'plugin_action_link' ), - 10, 2 ); // add a settings page link to the plugin description. use 2 for allowed vars
                    add_action ( 'admin_init', array (&$this, 'admin_init' ) ); // to register settings group
                    add_action ( 'admin_menu', array (&$this, 'admin_menu' ) ); // to setup menu link for settings page
                } else {
                    if (!preg_match("/feedblitz/i", $_SERVER['HTTP_USER_AGENT'])) {
                        add_action('template_redirect', array(&$this,'ol_feed_redirect'));
                        //add_action('init',array(&$this,'ol_check_url'));
                    }
                }
            }
            /**
            * PHP4 constructor
            */
            function fb_feedsmart() {
                $this->__construct();
            }

            /**************************************************************
            * plugin functions
            *************************************************************/


            /**************************************************************
            * admin functions
            *************************************************************/

            /** install
            * This function is called when the plugin activation hook is fired when
            * the plugin is first activated or when it is auto updated via admin.
            * use it to make any changes needed for updated version or to add/check
            * new database tables on first install.
            */
            function install(){
                $options = $this->get_options();
                if(!$options['version'] || version_compare($options['version'],$this->version,'<')){
                    // make any changes to this new versions options if needed and update
                    $options['version'] = $this->version;
                    update_option($this->db_option,$options);
                }
            }	
            /** handle_load_domain
            * This function loads the localization files required for translations
            * It expects there to be a folder called /lang/ in the plugin directory
            * that has all the .mo files
            */
            function handle_load_domain() {
                // get current language
                $locale = get_locale ();
                // locate translation file
                $mofile = WP_PLUGIN_DIR . '/' . plugin_basename ( dirname ( __FILE__ ) ) . '/lang/' . $this->plugin_domain . '-' . $locale . '.mo';
                // load translation
                load_textdomain ( $this->plugin_domain, $mofile );
            }

            /** get_options
            * This function sets default options and handles a reset to default options
            * return array
            */
            function get_options() {
                // default values
                $options = array ('feedsmart_url'=>'','feedsmart_comments_url'=>'');
                // get saved options unless reset button was pressed
                $saved = '';
                if (! isset ( $_POST ['reset'] )) {
                    $saved = get_option ( $this->db_option );
                }
                // assign values
                if (! empty ( $saved )) {
                    foreach ( $saved as $key => $option ) {
                        $options [$key] = $option;
                    }
                }
                // update the options if necessary
                if ($saved != $options) {
                    update_option ( $this->db_option, $options );
                }
                // return the options
                return $options;
            }

            /** admin_init
            * This function registers the settings group
            * it is called by add_action admin_init
            * options in the options page will need to be named using $this->db_option[option]
            */
            function admin_init(){
                // whitelist options
                register_setting( 'feedsmart_settings_options_group', $this->db_option ,array(&$this,'options_sanitize' ) );
                wp_register_script('feedsmart_admin_script',$this->plugin_url.'/js/admin-script.js',array('jquery'));
            }

            /** admin_menu
            * This function adds a link to the settings page to the admin menu
            * see http://codex.wordpress.org/Adding_Administration_Menus
            * it is called by add_action admin_menu
            */
            function admin_menu(){
                //$level = 'manage-options'; // for wpmu sub blog admins
                $level = 'administrator'; // for single blog intalls
                $hook = add_options_page ( 'Feedblitz Feedsmart Settings', 'Feedblitz Feedsmart', $level, $this->slug, array (&$this, 'options_page' ) );
                add_action('admin_head-'.$hook,create_function('','wp_enqueue_script("feedsmart_admin_script");'));
                add_filter('user_contactmethods',array(&$this,'add_contact_methods'));
            }
            /**
            * add feedblitze feed field to profile
            * 
            * @param mixed $contactmethods
            */
            function add_contact_methods($contactmethods){
                $contactmethods['feedblitz_feed'] = __('FeedBlitz Feed URL (for author page feed redirection)',$this->plugin_domain);
                return $contactmethods;
            }

            /** fb_membermail_action
            * This function adds a link to the settings page for the plugin on the plugins listing page
            * it is called by add filter plugin_action_links
            * @param $links - the links being filtered
            * @param $file - the name of the file
            * return array - the new array of links
            */
            function plugin_action_link($links, $file) {
                $this_plugin = plugin_basename ( __FILE__ );
                $slug = 'fb_membermail-settings';
                if ($file == $this_plugin) {
                    $links [] = "<a href='options-general.php?page={$this->slug}'>" . __ ( 'Settings', $this->plugin_domain ) . "</a>";
                }
                return $links;
            }
            /** options_sanitize
            * This is the callback function for when the settings get saved, use it to sanitize options
            * it is called by the callback setting of register_setting in admin_init
            * @param mixed $options - the options that were POST'ed
            * return mixed $options
            */
            function options_sanitize($options){
                //debugbreak();
                $feedsmart_url = trim($options['feedsmart_url']);
                if ($feedsmart_url != '' && false === strpos($feedsmart_url, '://')) {
                    $feedsmart_url = 'http://' . $feedsmart_url;
                }
                $options['feedsmart_url'] = $feedsmart_url;

                $feedsmart_comments_url = trim($options['feedsmart_comments_url']);
                if ($feedsmart_comments_url != '' && false === strpos($feedsmart_comments_url, '://')) {
                    $feedsmart_comments_url = 'http://' . $feedsmart_comments_url;
                }
                $options['feedsmart_comments_url'] = $feedsmart_comments_url;
                // do checks here
                if(is_array($options['catfeeds'])){
                    foreach($options['catfeeds'] as $cat => $fburl){
                        $options['catfeeds'][$cat] = trim($fburl);
                    }
                }
                if(is_array($options['catsexclude'])){
                    foreach($options['catsexclude'] as $key => $cat){
                        $options['catsexclude'][$key] = intval($cat);
                    }
                }
                return $options;
            }

            /**************************************************************
            * admin output
            *************************************************************/

            /** options_page
            * This function shows the page for saving options
            * it is called by add_options_page
            * You can echo out or use further functions to display admin style widgets here
            */
            function options_page(){
                $options = $this->get_options();
            ?>
            <div class="wrap">
            <h2>Feedblitz Feedsmart Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'feedsmart_settings_options_group' ); // the name given in admin init
                    // after here, put all the inputs and text fields needed ?>
                <p class="description" style="width:750px;"><?php _e('This plugin makes it easy to redirect 100% of traffic for your feeds to a FeedBlitz feed you have created. FeedBlitz can then track all of your feed subscriber traffic and usage and apply a variety of features you choose to improve and enhance your original WordPress feed.',$this->plugin_domain);?></p>
                <table cellpadding="3" cellspacing="3" class="widefat" style="width: 750px;">
                    <thead>
                        <tr><th colspan="2"><?php _e('Easy Setup Steps',$this->plugin_domain);?></th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td width="20">1)<td><?php printf(__('To get started,%screate a FeedBlitz feed for %s. This feed will handle all traffic for your posts.',$this->plugin_domain),' <a href="https://www.feedblitz.com/f?RssNew&Url='.home_url().'" target="_blank">',get_bloginfo().'</a>');?></td>
                        </tr>
                        <tr class="alt">
                            <td>2)<td><?php _e('Once you have created your FeedBlitz feed, enter its address into the field below (http://feeds.feedblitz.com/yourfeed)');?><br /><input type="text" name="<?php echo $this->db_option;?>[feedsmart_url]" value="<?php echo $options['feedsmart_url'];?>" size="45"></td>
                        </tr>
                        <tr>
                            <td width="20">3)<td><?php printf(__('Optional: If you also want to handle your WordPress comments feed using FeedBlitz, %screate a FeedBlitz comments feed%s and then enter its address below:',$this->plugin_domain),'<a href="https://www.feedblitz.com/f?RssNew&Url='.home_url().'/wp-commentsrss2.php" target="_blank">','</a>');?><br><input type="text" name="<?php echo $this->db_option;?>[feedsmart_comments_url]" value="<?php echo $options['feedsmart_comments_url'];?>" size="45"></td>
                        </tr>
                    </tbody>
                </table>
                <h3><?php _e('Category Feeds',$this->plugin_domain);?></h3>
                <p class="description"><?php _e('WordPress allows you to have separate feeds for each category.',$this->plugin_domain);?></p>
                <p class="description"><?php _e('You can redirect any of your blogs categories to a specific FeedBlitz feed.',$this->plugin_domain);?></p>
                <p class="description"><?php printf(__('eg, for a category of %s: Set up a FeedBlitz feed <a target="_blank" href="https://www.feedblitz.com/f?RssNew&Url=">here</a> and point it to your podcast category RSS with %s',$this->plugin_domain),'podcasts',home_url().'/category/podcasts/feed');?></p>
                <table cellpadding="3" cellspacing="3" class="widefat" style="width: 750px;">
                    <thead>
                        <tr>
                            <th><?php _e('Category',$this->plugin_domain);?></th>    
                            <th><?php _e('FeedBlitz Feed',$this->plugin_domain);?></th>
                            <th></th>    
                        </tr>
                    </thead>                                                                  
                    <tr>
                        <td><?php echo wp_dropdown_categories(array('show_count'=>false,'echo'=>0,'hierarchical'=>1,'depth'=>3,'id'=>'fbcat'));?></td>
                        <td><input type="text" size="60" id="catfeed"/></td>    
                        <td><p style="text-align: center"><a class="button-secondary" id="addcatfeed">Update / Add This Category To List</a></p></td>
                    </tr>
                </table>
                <h3><?php _e('Current redirected categories',$this->plugin_domain);?></h3>
                <p class="description"><?php _e('When someone tries to view the feed for the categories below, they will be redirected to the FeedBlitz feed shown',$this->plugin_domain);?></p>
                <table cellpadding="3" cellspacing="3" class="widefat" style="width: 750px;">
                    <thead> 
                        <tr>                              
                            <th><?php _e('Category',$this->plugin_domain);?></th>
                            <th><?php _e('FB Redirect URL',$this->plugin_domain);?></th>
                            <th><?php _e('Delete',$this->plugin_domain);?></th>
                        </tr>
                    </thead>
                    <tbody id="current">
                        <?php
                            $current = $options['catfeeds']; 
                            if(empty($current)){
                                echo '<tr id="none"><td colspan="2">'.__('No categories saved',$this->plugin_domain).'</td></tr>';
                            } else {
                                foreach($current as $cat => $fburl){
                                    echo '<tr id="cat-'.$cat.'" class="'.$cat.'"><td><input type="hidden" name="'.$this->db_option.'[catfeeds]['.$cat.']" value="'.$fburl.'"/><a href="'
                                    . get_category_feed_link($cat).'" target="_blank" title="'.get_category_feed_link($cat).'">'. get_catname($cat). '</a></td><td>'.$fburl.'</td><td><span style="padding: 1px; margin-left: 5px; background-color: red; border: 1px solid black; cursor:pointer;" class="fbdelete">X</span></td></tr>';
                                }
                            }
                        ?>
                    </tbody>
                </table>
                <h3><?php _e('Excluded Categories',$this->plugin_domain);?></h3>
                <table cellpadding="3" cellspacing="3" class="widefat" style="width: 750px;">
                    <thead>
                        <tr>
                            <th><?php _e('Category',$this->plugin_domain);?></th>    
                            <th></th>    
                        </tr>
                    </thead>                                                                  
                    <tr>
                        <td><?php echo wp_dropdown_categories(array('show_count'=>false,'echo'=>0,'hierarchical'=>1,'depth'=>3,'id'=>'fbcatexclude'));?></td>   
                        <td><p style="text-align: center"><a class="button-secondary" id="addcatexclude">Exclude This Category From Being Redirected</a></p></td>
                    </tr>
                </table>
                <h3><?php _e('Current excluded categories',$this->plugin_domain);?></h3>
                <p class="description"><?php _e('The following categories will not have the feed redirected',$this->plugin_domain);?></p>
                <table cellpadding="3" cellspacing="3" class="widefat" style="width: 750px;">
                    <thead> 
                        <tr>                              
                            <th><?php _e('Category',$this->plugin_domain);?></th>         
                            <th><?php _e('Delete',$this->plugin_domain);?></th>
                        </tr>
                    </thead>
                    <tbody id="currentexclude">
                        <?php
                            $currentexclude = $options['catsexclude'];
                            //debugbreak(); 
                            if(empty($currentexclude)){
                                echo '<tr id="noneexclude"><td colspan="2">'.__('No categories excluded',$this->plugin_domain).'</td></tr>';
                            } else {
                                foreach($currentexclude as $cat){
                                    echo '<tr id="catexclude-'.$cat.'" class="'.$cat.'"><td><input type="hidden" name="'.$this->db_option.'[catsexclude][]" value="'.$cat.'"/><a href="'
                                    . get_category_feed_link($cat).'" target="_blank" title="'.get_category_feed_link($cat).'">'. get_catname($cat). '</a></td><td><span style="padding: 1px; margin-left: 5px; background-color: red; border: 1px solid black; cursor:pointer;" class="fbdelete">X</span></td></tr>';
                                }
                            }
                        ?>
                    </tbody>
                </table>
                <h3><?php _e('Author Feeds',$this->plugin_domain);?></h3>
                <table cellpadding="3" cellspacing="3" class="widefat" style="width: 750px;">
                    <tbody>
                        <tr>
                            <td>
                                <p class="description"><?php printf(__('FeedSmart allows you to have special feeds just for authors. You can set the feed to be used for your user account in the %s section of your dashboard. (for multi author blogs, ask your guest bloggers to set their feedblitz feed in their profile if they want their own author feed)',$this->plugin_domain),'<a href="'.admin_url('profile.php').'">'.__('Your profile',$this->plugin_domain).'</a>');?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <h3><?php _e('Plugin Author',$this->plugin_domain);?></h3>
                <table cellpadding="3" cellspacing="3" class="widefat" style="width: 750px;">
                    <tbody>
                        <tr>
                            <td>
                                <p><img src="http://2.gravatar.com/avatar/7ac268015ed597d177e9f04422719e29?s=25" width="25" height="25" style="margin-right: 5px; margin-bottom: 5px;" align="left"><?php printf(__('This plugin is brought to you for free by Andy Bailey, creator of %s',$this->plugin_domain),'<a target="_blank" href="http://ql2.me/clp">CommentLuv Premium</a>');?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <input type="hidden" name="action" value="update" />
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                </p>
            </form>
            <?php
            }

            /**************************************************************
            * public output
            *************************************************************/


            /**************************************************************
            * helper functions
            *************************************************************/
            function ol_check_url() {
                $feedsmart_settings = $this->get_options();
                switch (basename($_SERVER['PHP_SELF'])) {
                    case 'wp-rss.php':
                    case 'wp-rss2.php':
                    case 'wp-atom.php':
                    case 'wp-rdf.php':
                        if (trim($feedsmart_settings['feedsmart_url']) != '') {
                            if (function_exists('status_header')) status_header( 302 );
                            header("Location:" . trim($feedsmart_settings['feedsmart_url']));
                            header("HTTP/1.1 302 Temporary Redirect");
                            exit();
                        }
                        break;
                    case 'wp-commentsrss2.php':
                        if (trim($feedsmart_settings['feedsmart_comments_url']) != '') {
                            if (function_exists('status_header')) status_header( 302 );
                            header("Location:" . trim($feedsmart_settings['feedsmart_comments_url']));
                            header("HTTP/1.1 302 Temporary Redirect");
                            exit();
                        }
                        break;
                }
            }

            function ol_feed_redirect() {
                global $wp, $wp_query, $feed, $withcomments; 

                $feedsmart_settings = $this->get_options();
                if (is_feed() && $feed != 'comments-rss2' && !is_single()) {
                    //debugbreak();
                    // check if this is a category feed
                    $objectid = $wp_query->get_queried_object_id();
                    if(!$objectid){
                        // non category feed
                        $fbfeedurl = $feedsmart_settings['feedsmart_url'];
                    } else {
                        // check if author page feed first
                        if($wp_query->is_author()){
                            // see if user has saved a feedblitz feed to their profile.
                            if(get_usermeta($objectid,'feedblitz_feed',true)){
                                $fbfeedurl = get_usermeta($objectid,'feedblitz_feed',true);
                            };
                        }
                        // check if this category is excluded
                        if(isset($feedsmart_settings['catsexclude'])){
                            if(is_array($feedsmart_settings['catsexclude'])){
                                foreach($feedsmart_settings['catsexclude'] as $cat){
                                    if($objectid == $cat){
                                        return;
                                    }
                                }
                            }
                        }
                        // check if category has a feedblitz feed 
                        if(isset($feedsmart_settings['catfeeds']) && is_array($feedsmart_settings['catfeeds'])){
                            foreach($feedsmart_settings['catfeeds'] as $cat => $fburl){
                                if($cat == $objectid){
                                    $fbfeedurl = $fburl;
                                    break;
                                }
                            }
                        }                       
                    }
                    // default to main feedblitz feed.
                    if($fbfeedurl == '' || $fbfeedurl == false){
                        $fbfeedurl = $feedsmart_settings['feedsmart_url'];
                    }

                    if ($fbfeedurl && function_exists('status_header')){
                        status_header( 302 );
                        header("Location:" . $fbfeedurl);
                        header("HTTP/1.1 302 Temporary Redirect");
                        exit();
                    }
                } elseif (is_feed() && ($feed == 'comments-rss2' || $withcomments == 1) && trim($feedsmart_settings['feedsmart_comments_url']) != '') {
                    if (function_exists('status_header')) status_header( 302 );
                    header("Location:" . trim($feedsmart_settings['feedsmart_comments_url']));
                    header("HTTP/1.1 302 Temporary Redirect");
                    exit();
                }
            }

        } // end class
    } // end if class not exists

    // start fb_feedsmart class engines
    if (class_exists ( 'fb_feedsmart' )) :
        $fb_membermail = new fb_feedsmart ( );
        // confirm warp capability
        if (isset ( $fb_feedsmart )) {
            // engage
            register_activation_hook ( __FILE__, array (&$fb_feedsmart, 'install' ) );
        }
        endif;
?>
