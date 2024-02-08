<?php
/**
 * Plugin Name: WooPOS
 * Plugin URI: https://woopos.dk/
 * Description: Synkronisering mellem din WooCommerce webshop og POS systemer.
 * Version: 1.0.0
 * Author: Kim Vinberg <support@woopos.dk>
 * Author URI: https://woopos.dk
 */
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

include("wooCommerceSettings.php");


/* Main plugin functions */
class wcioWGCSSP
{

    public $token;
    public $extrafield1;
    public $wooposapi;
    public $woopos_pos;

    public function __construct()
    {

        $this->token = get_option("woopos_token"); // Api key
        $this->woopos_pos = get_option("woopos_pos") ?? "";
 
        // Add cron sheduels
        add_filter('cron_schedules', array($this, 'add_cron_interval'));

        require dirname(__FILE__) . "/plugin-update-checker-5.3/plugin-update-checker.php"; // v5.3

        $myUpdateChecker = PucFactory::buildUpdateChecker(
            'https://github.com/uldtot/woopos/',
            __FILE__,
            'woopos'
        );

        //Set the branch that contains the stable release.
        $myUpdateChecker->setBranch('main');
	
		// Verify schedule_event
	    add_action('admin_init',  array($this, 'check_and_schedule_event'));
	    
        // Add options
        add_action('admin_init', array($this, 'custom_plugin_register_settings'));
        add_action('admin_menu', array($this, 'custom_plugin_setting_page'));

        // Add menu    
        register_activation_hook(__FILE__, array($this, 'activatePlugin'));
    }


    /*
	   *  Add options
	   * 
	   */
    function custom_plugin_register_settings()
    {

        register_setting('woopos_service_option_group', 'woopos_last_action'); // Used to make sure the plugin does not keep running when not neeeded.

    }
/**
 * Registers a custom plugin setting page in the WordPress admin menu.
 */
    function custom_plugin_setting_page()
    {

        add_options_page('WooPOS', 'WooPOS', 'manage_options', 'woopos_service_option',  array($this, 'optionsPage'));
    }

    /**
 * Renders the options page for WooPOS settings.
 */
    function optionsPage()
    { ?>
        <div class="wrap">
            <h2>Settings for WooPOS</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('woopos_service_option_group');
                ?>

                <table class="form-table">

                    <tr>
                        <th><label for="first_field_id">Last action:</label></th>

                        <td>
                            <p>Last action is when the plugin last had an action and will make sure the plugin doesnt spin in a loop.<br>This field value should look something like this <?php echo time(); ?>.<br>Do not empty this field unless its for testing.
                            </p>
                            <input type='text' class="regular-text" id="woopos_last_action_id" name="woopos_last_action" value="<?php echo get_option('woopos_last_action'); ?>"><br>
			    <input type='text' class="regular-text" id="woopos_last_action_id_2" name="woopos_last_action_2" value="<?php echo get_option('woopos_last_action_2'); ?>">
                        </td>
                    </tr>

                </table>

                <?php submit_button(); ?>

        </div>
    <?php
    }

/**
 * Adds a custom cron interval for running events every five minutes.
 *
 * @param array $schedules The existing cron schedules.
 *
 * @return array The modified cron schedules with the new interval.
 */
    function add_cron_interval($schedules)
    {
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display'  => esc_html__('Every Five Minute'),
        );
        return $schedules;
    }


/**
 * Checks and schedules recurring events for syncing data between WooCommerce and a POS service.
 */
function check_and_schedule_event() {
	
	// woopos_cron_sync_woo_service_pos
    $event_name = 'woopos_cron_sync_woo_service_pos';
	// Check if the event is already scheduled
    $next_scheduled = wp_next_scheduled($event_name);

    if ($next_scheduled === false) {
        // Event is not scheduled, schedule it
        wp_schedule_event(time(), 'five_minutes', $event_name);
    }
	
// woopos_cron_sync_service_pos_woo
    $event_name = 'woopos_cron_sync_service_pos_woo';
	// Check if the event is already scheduled
    $next_scheduled = wp_next_scheduled($event_name);

    if ($next_scheduled === false) {
        // Event is not scheduled, schedule it
        wp_schedule_event(time(), 'five_minutes', $event_name);
    }
	
	
}

/**
 * Activates the plugin by scheduling recurring events for syncing data between WooCommerce and a POS service.
 */
    function activatePlugin()
    {

        if (!wp_next_scheduled('woopos_cron_sync_woo_service_pos')) {
            wp_schedule_event(time(), 'five_minutes', 'woopos_cron_sync_woo_service_pos');
        }

        if (!wp_next_scheduled('woopos_cron_sync_service_pos_woo')) {
            wp_schedule_event(time(), 'five_minutes', 'woopos_cron_sync_service_pos_woo');
        }
    }

	/**
 * Makes an HTTP request to a specified API endpoint using cURL.
 *
 * @param string $method   The HTTP method (e.g., "GET", "POST", "PUT").
 * @param string $endpoint The API endpoint to be called.
 * @param mixed  $data     The data to be sent with the request (optional).
 *
 * @return array|bool The response from the API in the form of an associative array, or false on failure.
 */
    function call($method, $endpoint, $data = false)
    {
        
        $url = 'https://api.woopos.dk/' . $this->woopos_pos . '/' . $endpoint . '';
        $curl = curl_init();
        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
             case "PATCH":
                    curl_setopt($curl, CURLOPT_POST, 1);
                    if ($data) {
                        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                    }
                    break;
            default:
                if ($data) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'posapi ' . $this->token, // Needed most POS to connect
            'extrafield1 ' . $this->extrafield1, // Needed by some POS
            'wooposapi ' . $this->wooposapi, // Needed to use WooPos API
        ));
  
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status >= 300) {
            exit; // Fixes infinite loop when wrong API key
        }
        curl_close($curl);


        sleep(1); // Delay execution for X seconds
        return json_decode($result, true);
    }

    /**
 * Searches through a multi-dimensional array for all occurrences where a specific key has a given value.
 *
 * @param array  $array The array to be searched.
 * @param string $key   The key to match against.
 * @param mixed  $value The value to match for the specified key.
 *
 * @return array An array containing all subarrays that meet the specified key-value condition.
 */
    function search($array, $key, $value)
    {
          $results = array();

          if (is_array($array)) {
                if (isset($array[$key]) && $array[$key] == $value) {
                      $results[] = $array;
                }

                foreach ($array as $subarray) {
                      $results = array_merge($results, $this->search($subarray, $key, $value));
                }
          }

          return $results;
    }

// Define a function to check if a string follows a specified format
function checkFormat($string, $format) {

    // If empty
    if(empty($string)) { return false; }

    // Escape any backslashes in the format to avoid them being interpreted as escape characters in the regex
    $pattern = '/^' . preg_quote($format, '/') . '$/';

    // Use the preg_match function to check if the string matches the pattern
    if (preg_match($pattern, $string)) {
        return true; // Return true if the string follows the specified format
    } else {
        return false; // Otherwise, return false
    }
}


/*
// Test examples
$string1 = "1234-4567-7890-1234";
$string2 = "9876543210987654";

// Check the format "****-****-****-****"
$format1 = "****-****-****-****";
if (checkFormat($string1, $format1)) {
    echo "Strengen følger det forudbestemte format 1."; // If the string follows the format, print this message
} else {
    echo "Strengen følger ikke det forudbestemte format 1."; // Otherwise, print this message
}
echo "<br>";

// Check the format "****************"
$format2 = "****************";
if (checkFormat($string2, $format2)) {
    echo "Strengen følger det forudbestemte format 2."; // If the string follows the format, print this message
} else {
    echo "Strengen følger ikke det forudbestemte format 2."; // Otherwise, print this message
}
*/

}

// Include callAPI
$wcioWGCSSP = new wcioWGCSSP();
$woopos_pos = $wcioWGCSSP->woopos_pos;


include(dirname(__FILE__) . "/includes/callApi.php");

