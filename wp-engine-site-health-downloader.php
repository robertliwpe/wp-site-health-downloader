<?php
/*
 * Plugin Name: WP Engine Site Health Info Downloader
 * Description: Outputs site health info including database and table sizes into a text file that gets downloaded.
 * Version: 5.0
 */
 
// Create custom admin page
add_action( 'admin_menu', 'shid_create_menu' );
function shid_create_menu() {
	add_menu_page( 'Site Health Info Downloader', 'Site Health Info Downloader', 'manage_options', 'shid-settings', 'shid_settings_page', 'dashicons-admin-generic', 6  );
}

// Create settings page content
function shid_settings_page() {
    ?>
    <div class="wrap">
        <h2>WP Engine Site Health Info Downloader</h2>
        <p>Click the button below to download the site health info in a text file.</p>
        <p>Send the file created to your WP Engine Contact.</p>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <input type="hidden" name="action" value="shid_download_file" />
            <?php wp_nonce_field( 'shid_download_file_nonce' ); ?>
            <?php submit_button( 'Download Site Health File' ); ?>
        </form>
    </div>
    <?php
}

// Handle form submission and create file
add_action( 'admin_post_shid_download_file', 'shid_download_file' );
function shid_download_file() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    check_admin_referer( 'shid_download_file_nonce' );
    
    global $wpdb;
    $wordpress_url = get_site_url(); // get the site URL
    $php_url = parse_url( get_site_url(), PHP_URL_HOST );
    $multisite_check = is_multisite();
    $tables = $wpdb->get_results( "SHOW TABLE STATUS", ARRAY_A );
    $plugins = get_plugins(); // get all plugins
    // $plugin_updates = get_plugin_updates(); // get plugin updates
    // $wp_plugin_updates = wp_update_plugins(); // get plugin updates (not sure whether this is the right one or the above one)
    // $active_plugins = get_option('active_plugins'); // Get list of active plugins
    $active_theme = wp_get_theme(); // Get active theme
    $total_db_size_mb = round($wpdb->get_var("SELECT SUM(data_length + index_length) / 1024 / 1024 FROM information_schema.TABLES WHERE table_schema='" . DB_NAME . "'")); // Get total MB size of the database
    $total_site_size_mb = round(dir_size(ABSPATH) / 1024 / 1024); // Get total MB size of the entire wordpress site
    $uploads_size_mb = wp_uploads_folder_size(); // Get total MB size of uploads directory
    $php_version = phpversion(); // Get PHP version
    $mysql_version = $wpdb->get_var("SELECT VERSION()"); // Get MySQL Version
    $server_info = get_server_options();
    $wp_version = get_bloginfo('version');
    // $inactive_plugins = get_option('inactive_plugins');
    // $wp_plugins_list = list_wp_plugins(); // doesn't work

    // Create string of data
    $data = 'URL : ' . $wordpress_url . "\n\n";	
    $data .= 'WordPress Core Version: ' . $wp_version . "\n\n";
    $data .= 'Active Theme: ' . $active_theme . "\n\n";
    // $data .= 'Active Plugins: ' . implode(', ', $active_plugins) . "\n\n";
    // $data .= 'Inactive Plugins: ' . implode(', ', $inactive_plugins) . "\n\n";
    if ($multisite_check) {
        $num_sites = get_blog_count();
        $data .= 'This is a multisite with ' . $num_sites . ' child sites.' . "\n\n";
      } else {
        $data .= 'This is not a multisite.' . "\n\n";
      }
    $data .= 'PHP Version: ' . $php_version . "\n\n";
    $data .= 'MySQL Version: ' . $mysql_version . "\n\n";
    $data .= 'Total Site Size (MB): ' . $total_site_size_mb . "\n\n";
    $data .= 'Uploads Folder Size (MB): ' . $uploads_size_mb . "\n\n";
    $data .= "====================================================\n\n";
    $data .= 'Database Name: ' . DB_NAME . "\n\n";
    $data .= 'Total Database Size (MB): ' . $total_db_size_mb . "\n\n";
    foreach( $tables as $table ) {
        $data .= 'Table Name: ' . $table['Name'] . "\n";
        $data .= 'Rows: ' . $table['Rows'] . "\n";
        $data .= 'Data Size: ' . $table['Data_length'] . "\n";
        $data .= 'Index Size: ' . $table['Index_length'] . "\n\n";
    }
    $data .= "====================================================\n\n";

    // Iterate through each plugin for update - INCORRECT does not fetch from repo whether there is update available
    // foreach ($plugins as $plugin) {
    //     if (array_key_exists($plugin['Name'], $plugin_updates)) {
    //       $data .= 'Plugin ' . $plugin['Name'] . ' is out of date.' . "\n";
    //     } else {
    //       $data .= 'Plugin ' . $plugin['Name'] . ' is up to date.' . "\n";
    //     }
    //   }

    // Iterate through plugins and check if there is an update - v2 - Does not work
    // foreach($wp_plugins_list as $list) {
    //    foreach($list as $option) {
    //         $data .= $$option . "\n";
    //    }
    // }

    $activated_plugins = array();
    $deactivated_plugins = array();

    foreach($plugins as $key => $val){
        if(is_plugin_inactive($key)){
            array_push($deactivated_plugins, $key);
        } else {
            array_push($activated_plugins, $key);
        }
    }

    $data .= "Active Plugins:\n\n";
    foreach( $activated_plugins as $activated ) {
        $data .= $activated . "\n";
    }

    $data .= "\nInactive Plugins:\n\n";
    foreach( $deactivated_plugins as $deactivated ) {
        $data .= $deactivated . "\n";
    }

    $data .= "\n====================================================\n\n";

    $data .= "Server Options: \n\n";
    foreach($server_info as $option => $value) {
        $data .= 'Option ' . $option . ' : ' . $value . "\n";
      }

    $data .= "\n====================================================\n\n";
      
    // Using the free Similar Rank API endpoint - hardcoding API key for ease of use
    $data .= "SimilarWeb Site Rank API Data:\n\n";
	
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.similarweb.com/v1/similar-rank/" . $php_url . "/rank?api_key=2fc7793f85fa45e29725af6d070d65a2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
          "cache-control: no-cache"
        ),
      ));
      
    $response = curl_exec($curl);
    curl_close($curl);

    $data .= $response . "\n\n";


    // $response_sw = file_get_contents('https://api.similarweb.com/v1/similar-rank/' . $php_url . '/rank?api_key=2fc7793f85fa45e29725af6d070d65a2');
    // $response_decode = json_decode($response_sw, true);
    // $data .= $response_decode;

    // Send file headers
    header( 'Content-Type: text/plain' );
    header( 'Content-Disposition: attachment; filename="wp-engine-site-health-info-' . date( 'Y-m-d-H-i-s' ) . '.txt"' );
    
    // Output data
    echo $data;
    exit();
}

// Calculate directory size recursively
function dir_size($directory) {
    $size = 0;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file){
        $size += $file->getSize();
    }
    return $size;
}

// Fetch WP Uploads directory size

function wp_uploads_folder_size() {
    $upload_dir = wp_upload_dir();
    $files = scandir($upload_dir['basedir']);
    $size = 0;
    
    foreach ($files as $file) {
      if (is_file($upload_dir['basedir'] . '/' . $file)) {
        $size += filesize($upload_dir['basedir'] . '/' . $file);
      }
    }
    
    return round($size / 1024 / 1024, 2) . ' MB';
  }

// Fetch WP Uploads directory size - INCORRECT doesn't return val
// function get_wordpress_uploads_directory_size() {
//     $upload_dir = wp_get_upload_dir();
//     $directory_size = 0;
//     if ($handle = opendir($upload_dir['basedir'])) {
//         while (false !== ($entry = readdir($handle))) {
//             if ($entry != "." && $entry != "..") {
//                 $directory_size += filesize($upload_dir['basedir'] . '/' . $entry);
//             }
//         }
//         closedir($handle);
//     }
//     return $directory_size;
// }

// Get Server Options
function get_server_options() {
    $options = array();
    $options['memory_limit'] = ini_get('memory_limit');
    $options['max_execution_time'] = ini_get('max_execution_time');
    $options['upload_max_filesize'] = ini_get('upload_max_filesize');
    $options['post_max_size'] = ini_get('post_max_size');
    return $options;
}

// DOES NOT WORK - check updates
// function list_wp_plugins($args = array()) {
//     $defaults = array(
//         'fields' => 'all',
//         'plugin_status' => 'all',
//     );
//     $r = wp_parse_args( $args, $defaults );
 
//     if ( !isset($r['fields']) || 'all' == $r['fields'] ) {
//         $r['fields'] = array('name', 'slug', 'version');
//     }
//     $plugins = get_plugins();
//     $updates = get_site_transient( 'update_plugins' );
//     foreach ( $plugins as $plugin_file => $plugin_data ) {
//         $plugin_data['update'] = false;
//         if ( isset( $updates->response[ $plugin_file ] ) ) {
//             $plugin_data['update'] = true;
//         }
//         $list[] = array_intersect_key( $plugin_data, array_flip( $r['fields'] ) );
//     }
//     return $list;
    
// }

