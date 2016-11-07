<?php
/**
 * Plugin Name: Shift8 Treb
 * Plugin URI: https://github.com/stardothosting/shift8-treb
 * Description: This plugin integrates your TREB (Toronto Real Estate Board) listings into your Wordpress site
 * Version: 1.0.1
 * Author: Shift8 Web 
 * Author URI: https://www.shift8web.ca
 * License: GPLv3
 */

// Requires
require_once('wp-background-processing.php');

// Activate hook
function shift8_treb_activate() {

	// Create media upload sub folder
	$upload = wp_upload_dir();
	$upload_dir = $upload['basedir'];
	$upload_dir = $upload_dir . '/treb';
	if (! is_dir($upload_dir)) {
		mkdir( $upload_dir, 0774 );
	}
}

register_activation_hook( __FILE__, 'shift8_treb_activate' );


// create custom plugin settings menu
add_action('admin_menu', 'shift8_treb_create_menu');

function shift8_treb_create_menu() {
	//create new top-level menu
	if ( empty ( $GLOBALS['admin_page_hooks']['shift8-settings'] ) ) {
		add_menu_page('Shift8 Settings', 'Shift8', 'administrator', 'shift8-settings', 'shift8_main_page' , 'dashicons-building' );
	}
	add_submenu_page('shift8-settings', 'Shift8 Settings', 'TREB Settings', 'manage_options', __FILE__.'/custom', 'shift8_treb_settings_page');
	//call register settings function
	add_action( 'admin_init', 'register_shift8_treb_settings' );
}


function register_shift8_treb_settings() {
	//register our settings
	register_setting( 'shift8-treb-settings-group', 's8_agent_id' );
	register_setting( 'shift8-treb-settings-group', 's8_treb_user' );
	register_setting( 'shift8-treb-settings-group', 's8_treb_pass' );
	register_setting( 'shift8-treb-settings-group', 's8_treb_minlist' );
	register_setting( 'shift8-treb-settings-group', 's8_treb_agent_exclude' );
	// Import progress option
	add_option( 'shift8-treb-import-progress', '0', '', 'yes');
}

// Register admin scripts 
function load_shift8_treb_wp_admin_style() {
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_script('jquery-ui-button');
	wp_enqueue_script('jquery-ui-progressbar');
        // admin always last
        wp_enqueue_style( 'shift8_treb_css', plugin_dir_url( __FILE__ ) . 'css/shift8_treb_admin.css' );
        wp_enqueue_media();
        wp_enqueue_script( 'shift8_treb_script', plugin_dir_url( __FILE__ ) . 'js/shift8_treb_admin.js' );
}
add_action( 'admin_enqueue_scripts', 'load_shift8_treb_wp_admin_style' );

// Admin welcome page
if (!function_exists('shift8_main_page')) {
	function shift8_main_page() {
	?>
	<div class="wrap">
	<h2>Shift8 Plugins</h2>
	Shift8 is a Toronto based web development and design company. We specialize in Wordpress development and love to contribute back to the Wordpress community whenever we can! You can see more about us by visiting <a href="https://www.shift8web.ca" target="_new">our website</a>.
	</div>
	<?php
	}
}


// Admin settings page
function shift8_treb_settings_page() {

	if (isset($_POST['test_button']) && check_admin_referer('test_button_clicked')) {
		$treb_data = treb_get_csv();
		treb_import_data($treb_data);
	}
?>
<div class="wrap">
<h2>Shift8 TREB</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'shift8-treb-settings-group' ); ?>
    <?php do_settings_sections( 'shift8-treb-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">TREB Agent ID</th>
        <td><input type="text" name="s8_agent_id" value="<?php echo esc_attr( get_option('s8_agent_id') ); ?>" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">TREB username</th>
        <td><input type="text" name="s8_treb_user" value="<?php echo esc_attr( get_option('s8_treb_user') ); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">TREB password</th>
        <td><input type="password" name="s8_treb_pass" value="<?php echo esc_attr( get_option('s8_treb_pass') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Minimum Listing Price</th>
        <td><input type="text" name="s8_treb_minlist" value="<?php echo esc_attr( get_option('s8_treb_minlist') ); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">TREB Exclude Agent IDs</th>
        <td><input type="text" name="s8_treb_agent_exclude" value="<?php echo esc_attr( get_option('s8_treb_agent_exclude') ); ?>" /></td>
        </tr>
    </table>
    <?php 
	submit_button(); 
	?>
	</form>
	<hr>
	<p>Choose a date to import data : <input type="text" id="datepicker-treb"></p>
	<a id="treb-import-button" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=shift8-treb%2Fshift8-treb.php%2Fcustom&process=treb_images'), 'process' ); ?>">Import TREB Images</a>
	<div class="treb-import-container">
	<div class="treb-import-progress"></div>
	</div>
</div>
<?php 
}

add_action( 'wp_ajax_treb_import_progress', 'treb_import_progress_callback' );
function treb_import_progress_callback() {
	global $wpdb; // this is how you get access to the database
	$progress = intval(get_option('shift8-treb-import-progress'));
	error_log('the progress!!! : ' . $progress);
        echo $progress;
	wp_die(); // this is required to terminate immediately and return a proper response
}


function treb_get_csv($date, $avail) {
        $the_day = $date[0];
        $the_mon = $date[1];
        $the_yr = $date[2];
        $avail_opt = "avail";
        $s8_treb_user = esc_attr( get_option('s8_treb_user') );
        $s8_treb_pass = esc_attr( get_option('s8_treb_pass') );
	$url = "http://3pv.torontomls.net/data3pv/DownLoad3PVAction.asp?user_code=" . $s8_treb_user . "&password=" . $s8_treb_pass . "&sel_fields=*&dlDay=" . $the_day . "&dlMonth=" . $the_mon . "&dlYear=" . $the_yr . "&order_by=&au_both=" . $avail_opt . "&dl_type=file&incl_names=yes&use_table=MLS&send_done=no&submit1=Submit&query_str=lud%3E%3D%27" . $the_yr . $the_mon . $the_day . "%27";

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);

	// Parse CSV variable and construct an array
	$data_array = array();
	$data_counter = false;
	foreach (preg_split("/((\r?\n)|(\r\n?))/", $data) as $data_line){ 
		// Skip first line
		if (!$data_counter) {
			$data_counter = true;
			continue;
		}
		$data_array[] = str_getcsv($data_line);
	}
	return $data_array;
}

add_action( 'init', 'process_handler' );
function process_handler() {
	$treb_import = new StdClass;
	$treb_import->treb_import_process = new Treb_Import_Process();

        if ( 'treb_images' === $_GET['process'] ) {
		// Parse date , otherwise assign current date
		if ($_GET['date']) {
			$date = explode("-", $_GET['date']);
		} else {
			$date = explode("-", date('d-m-Y'));
		}
                $treb_data = treb_get_csv($date);
		$loop_count = 0;
	        foreach ($treb_data as $item) {
			$loop_count++;
			// Prep multidimensional array
			$item_array = array( 
					count($treb_data), 
					$item,
					$loop_count
					);
			// Queue the import
			$treb_import->treb_import_process->push_to_queue($item_array);

		}
		$treb_import->treb_import_process->save()->dispatch();
        }
}

function treb_get_images($remote_url, $remote_user, $remote_pass, $local_file) {
//	ignore_user_abort(true);
//	set_time_limit(0);
	try {
		$ch = curl_init();
		$fp = fopen($local_file, 'w');
		curl_setopt_array( $ch, array(
			CURLOPT_URL => $remote_url,
			CURLOPT_HEADER => 0,
			CURLOPT_VERBOSE => 0,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_BINARYTRANSFER => 1,
			CURLOPT_CONNECTTIMEOUT => 140,
			CURLOPT_TIMEOUT => 300,
			CURLOPT_NOSIGNAL => 1,
			CURLOPT_FILE => $fp
			)
		);
		// Set CURL to write to disk
		// Execute download
		$response = curl_exec($ch); 
		if (FALSE === $response) {
			throw new Exception(curl_error($ch), curl_errno($ch));
		}
	} catch(Exception $e) {
		trigger_error(sprintf(
	        'Curl failed with error #%d: %s',
	        $e->getCode(), $e->getMessage()),
	        E_USER_ERROR);
	}
		curl_close($ch);
		fclose($fp);
}


class Treb_Import_Process extends WP_Background_Process {

	protected $action = 'treb_import_process';

	protected function task( $item ) {
		// Actions to perform
	        $ftp_server = "3pv.torontomls.net";
	        $ftp_user = esc_attr( get_option('s8_treb_user') ) . '%40photos';
	        $ftp_pass = esc_attr( get_option('s8_treb_pass') );


		$description = $item[1][2];
		$streetname = $item[1][273];
		$streetnumber = $item[1][275];
		$streetsuffix = $item[1][276];
		$address = $item[1][3];
		$postalcode = $item[1][330];
		$bathrooms = $item[1][10];
		$bedrooms = $item[1][15];
		$bedplus = $item[1][16];
		$houseclass = $item[1][29];
		$extras = $item[1][69];
		$listagent = $item[1][111];
		$listprice = $item[1][120];
		$mlsnumber = $item[1][130];
		$squarefoot = $item[1][269];
		$virtualtour = $item[1][292];
		$pictures = $item[1][174];
		$inputdate = $item[1][174];
		$lastupdate = $item[1][123];
		$solddate = $item[1][24];
		$agentid = $item[1][333]; 
	
		// FTP get images
		$upload = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		$upload_dir = $upload_dir . '/treb/' . $mlsnumber;
		if (! is_dir($upload_dir)) {
			mkdir( $upload_dir, 0774 );
		}

		$mlsimage = substr($mlsnumber, -3);
		$local_file = $upload_dir . '/' . $mlsnumber;

		if ($mlsnumber) {
			for ($i = 1;$i < 10;$i++) {
				if ($i == 1) {
					$local_image  = $local_file . '.jpg';
					$remote_image = $mlsnumber . '.jpg';
				} else {
					$local_image = $local_file . '_' . $i . '.jpg';
					$remote_image = $mlsnumber . '_' . $i . '.jpg';
				}
				if (!file_exists($local_image) && @filesize($local_image) < 100) {
					treb_get_images('ftp://' . $ftp_user . ':' . $ftp_pass . '@3pv.torontomls.net/mlsphotos/' . $i . '/' . $mlsimage . '/' . $remote_image, $ftp_user, $ftp_pass, $local_image);
				} else { 
					error_log('skipping, file exists : ' . $local_image);
				}
			}

	                // Create post
	                $treb_post = array(
	                        'post_title'    => wp_strip_all_tags( 'TREB Listing : ' . $streetnumber . ' ' . $streetname . ' ' . $mlstnumber ),
	                        'post_content'  => $description,
	                        'post_status'   => 'publish',
	                        'post_author'   => 1,
	                        'tax_input'     => array(
	                        'post_tag' => $mlsnumber,
	                        ),
	                );
	                error_log('posting for : ' . $mlsnumber);
	                wp_insert_post( $treb_post ); 


		}
		$progress_total = $item[0];
		$progress_current = $item[2];
		$progress_percentage = $progress_current / $progress_total * 100;
		error_log('progress percentage : ' . $progress_percentage);
		update_option('shift8-treb-import-progress', $progress_percentage);
		return false;
	}

	protected function complete() {
		parent::complete();
	}

}
