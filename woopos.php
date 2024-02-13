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
    public $pagelength;
    //public $codepattern;


    public function __construct()
    {

        $this->token = get_option("woopos_token"); // Api key
        $this->extrafield1 = get_option("woopos_extrafield1");
        $this->wooposapi = get_option("woopos_api");
        $this->woopos_pos = get_option("woopos_pos") ?? "";
       // $this->pagelength = get_option('woopos_woopos_pagelength'); // pageLength setting


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

        // Run this code if the gift card plugin is Woo Gift Card
        add_action('woopos_cron_sync_woo_pos', array($this, 'woopos_cron_sync_woo_pos')); // Sync from Woo to POS
        add_action('woopos_cron_sync_pos_woo', array($this, 'woopos_cron_sync_pos_woo')); // Sync from POS to Woo
      //  add_action('admin_init',  array($this, 'ywgc_code_pattern')); // Sets the Giftcard pattern

    }


    /*
	   *  Add options
	   * 
	   */
    public function custom_plugin_register_settings()
    {

        register_setting('woopos_service_option_group', 'woopos_last_action'); // Used to make sure the plugin does not keep running when not neeeded.

    }
    /**
     * Registers a custom plugin setting page in the WordPress admin menu.
     */
    public function custom_plugin_setting_page()
    {

        add_options_page('WooPOS', 'WooPOS', 'manage_options', 'woopos_service_option',  array($this, 'optionsPage'));
    }

    /**
     * Renders the options page for WooPOS settings.
     */
    public function optionsPage()
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
    public function add_cron_interval($schedules)
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
    public function check_and_schedule_event()
    {

        // woopos_cron_sync_woo_pos
        $event_name = 'woopos_cron_sync_woo_pos';
        // Check if the event is already scheduled
        $next_scheduled = wp_next_scheduled($event_name);

        if ($next_scheduled === false) {
            // Event is not scheduled, schedule it
            wp_schedule_event(time(), 'five_minutes', $event_name);
        }

        // woopos_cron_sync_pos_woo
        $event_name = 'woopos_cron_sync_pos_woo';
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
    public function activatePlugin()
    {

        if (!wp_next_scheduled('woopos_cron_sync_woo_pos')) {
            wp_schedule_event(time(), 'five_minutes', 'woopos_cron_sync_woo_pos');
        }

        if (!wp_next_scheduled('woopos_cron_sync_pos_woo')) {
            wp_schedule_event(time(), 'five_minutes', 'woopos_cron_sync_pos_woo');
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
    public function call($method, $endpoint, $data = false)
    {

        $url = 'https://api.woopos.dk/' . $this->woopos_pos . '' . $endpoint . '';
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
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
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
            'posapi: ' . $this->token, // Needed most POS to connect
            'extrafield1: ' . $this->extrafield1, // Needed by some POS
            'wooposapi: ' . $this->wooposapi, // Needed to use WooPos API
        ));

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status >= 300) {
            $this->log("Api status code was: $status\r\n
            Pos: " . $this->woopos_pos . "\r\n
            Url: $url
            ");
            exit; // Fixes infinite loop when wrong API key
        }
        curl_close($curl);

        $decodedResult = json_decode($result, true);

        sleep(1); // Delay execution for X seconds
        return $decodedResult;
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
    public function search($array, $key, $value)
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
    public function checkFormat($string, $format)
    {
        return true; // trying without any validation of pattern.

        // If empty
        if (empty($string)) {
            return false;
        }

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

    public function log($message)
    {

        if (defined('WP_DEBUG') && WP_DEBUG) {

            // Log the message to the debug.log file
            error_log($message);
        }
    }


    // Makre sure the Giftcard plugin pattern is correct for this POS.
    function ywgc_code_pattern()
    {
        $word = "-";

        // Test if string contains the word 
        if (strpos(get_option("ywgc_code_pattern"), $word) !== false) {

            update_option('ywgc_code_pattern', $this->codepattern); // Make sure YITH woocommerce gift card plugin uses the correct pattern.

        }
    }

    // Loops all WooCommerce giftcards and creates the in POS if they do not exist.
    function woopos_cron_sync_woo_pos()
    {

        global $wpdb;
        $giftCardTable = $wpdb->prefix . "posts"; // Table for giftcards

        // If its less than 5 minutes ago since last action, then dont? allow this ro run again.
        $woopos_last_action_2 = get_option('woopos_last_action');
        if ($woopos_last_action_2 > (time() - 300)) {
                  //return;
        }

        $this->log("---------- LOG woopos_cron_sync_woo_pos START ------------");
        $this->log("It was more than more minutes ago since last run. Continue.");


        // Update last action
        update_option('woopos_last_action', time());

        // Get Gift cards from database
        $query = $wpdb->prepare("SELECT * FROM $giftCardTable WHERE post_type = %s ORDER BY ID DESC", 'gift_card');
        $wooGiftCards = $wpdb->get_results($query);

        // Get POS giftcards
        // Sets the amount of gift cards per page.
        $pageLength = $this->pagelength ?? 9999; // Defaults to a low number to mkae sure it works with most POS.

        $this->log("PageLength set to: $pageLength");
        // Make the full list of giftcards from POS.
        $POSGiftcards = array();
        $page = 0;


        while (true) {

            // We need to loop all pages
            // Now we do the query with the paging.
            $pageStart = $pageLength * $page;
            $query = array(
                "pageLength" => $pageLength,
                "pageStart" => $pageStart,
            ); // Start from page 1 (0)

            $queryGiftcards = $this->call("GET", "/giftcards", $query);
            
            // Merge all giftcards from POS. into one array.
            $POSGiftcards = array_merge($POSGiftcards, $queryGiftcards["giftcards"]);

            // If hasMore is false or there is no giftcards, break
            if ($queryGiftcards["hasMore"] == false || $page >= 1000) {
                break;
            }

            $page++;
        }


        
        $this->log(json_encode($POSGiftcards));

       


        // Loop all WooCommerce giftcards then in each giftcard we loop POS. to find it.
        foreach ($wooGiftCards as $card) {

            // Update last action
            update_option('woopos_last_action', time());

            $giftcardno = $card->post_title; // YITH
            $balance = floatval(get_post_meta($card->ID, "_ywgc_amount_total", true));  // This is initial balance
            $WooRemaining = floatval(get_post_meta($card->ID, "_ywgc_balance_total", true)); // This is remaining

            $searchGiftCardRaw = $this->search($POSGiftcards, 'giftcardno', $giftcardno);
            $searchGiftCardCount = count($searchGiftCardRaw);

            if ($searchGiftCardCount > 0) {

                $giftcard = $searchGiftCardRaw[0];

                // First check if the giftcard is available in POS. variable
                // We cannot break this loop until we auctually find it, because we have to check all cards
                if ($giftcardno == $giftcard["giftcardno"]) {

                    // If gift card was found at POS.
                    // Match values to make sure this isnt an outdated card.
                    //$POSAmount = $queryGiftcards["content"]["0"]["amount"]; // Overwridden to fix error.
                    $POSAmount = floatval($giftcard["amount"]); // Full amount remaining

                    // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                    // If the card in WooCommerce have been used more then the one in POS., then update POS.
                    if ($WooRemaining < $POSAmount) {

                        // If WooCommerce gift card ave more spent on it, then we need to update POS.
                        // Now updat the amount spent.
                        $POSNewAmount = $WooRemaining; // Full amount minus the remaining from wooCommerce gives the amount spent
                        $giftcardData = [
                            'amount' => (float)$POSNewAmount
                        ];

                        $this->log("Updating giftcard $giftcardno (id: ".$giftcard["id"].") in POS with value $POSNewAmount");

                            // Update giftcard in POS.
                            $updatePOSGiftcard = $this->call("PATCH", "/giftcards/" . $giftcard["id"] . "", $giftcardData);
                            $this->log("Response: ".json_encode($updatePOSGiftcard)."");
                            continue;
                       
                    }
                }

            } else if ($searchGiftCardCount == 0) { // IF card wasnt found in POS.

                // It wasnt dead, now create it in POS. since its not there. 
                // First check what the balance auctually is.
                if ($balance == 0) {

                    // The balance was 0, most likely due to the card wasnt created with a balance, then we need to use remaining as balance and fix the card.
                    $giftcardAmount = $WooRemaining;
                    
                } else {

                    $giftcardAmount = $balance;
                }

                // Send to API
                $giftcard = [
                    "giftcardno" => $giftcardno,
                    "amount" => (float)$giftcardAmount,
                ];

                $createPOSGiftcard = $this->call("POST", "/giftcards",  $giftcard);
                continue;

            }
        }
    }

    // Tjekker POS. gift cards og opretter dem i WooCommerce Gift Cards hvis de ikke allerede findes. Hvis de findes i WooCommerce Gift Cards gør den ikke mere
    // THis function does ONLY check POS., not WooCommerce.
    function woopos_cron_sync_pos_woo()
    {
        global $wpdb;
        // Start run
        // If its less than 5 minutes ago since last action, then dont? allow this ro run again.
        $woopos_last_action_2 = get_option('woopos_last_action_2');
        if ($woopos_last_action_2 > (time() - 300)) {
                  return;
        }

        $this->log("---------- LOG woopos_cron_sync_pos_woo START ------------");
        $this->log("It was more than more minutes ago since last run. Continue.");

        // THis function should check service POS and do the sme as the Woo function did.

        $giftCardTable = $wpdb->prefix . "posts";

        // Sets the amount of gift cards per page.
        $pageLength = $this->pagelength ?? 9999; // Defaults to a low number to mkae sure it works with most POS.

        $this->log("PageLength set to: $pageLength");

        // Make the full list of giftcards from POS.
        $POSGiftcards = array();
        $page = 0;
        while (true) {

            // Now we do the query with the paging.
            $pageStart = $pageLength * $page;

            $query = array("pageLength" => $pageLength, "pageStart" => $pageStart); // Start from page 1 (0)
            $giftcards = $this->call("GET", "/giftcards", $query);


            $this->log("Found the following list of giftcards: " . json_encode($giftcards) . "");

            // Loops all POS. giftcard
            foreach ($giftcards["giftcards"] AS $id => $card) {

                
                // Update last action
                update_option('woopos_last_action_2', time());

                $giftcardno = $card["giftcardno"]; //724503989151
                $amount = floatval($card["amount"]); // This is amount remainning in POS

                // Card exmaple:
                // {"giftcardno":"584483513","amount":999}

                // Validate giftcardno
                if($card["giftcardno"] == "" || $amount == 0) {
                    continue;
                }
              /*   if (!$this->checkFormat($card["giftcardno"], $this->codepattern)) {
                    
                    $this->log("".$card["giftcardno"]." failed checkFormat. Codepattern: ".$this->codepattern."");
                    continue;

                }*/


                // Make woo data format of giftcard and search for the giftcard
                $query = $wpdb->prepare("SELECT * FROM $giftCardTable WHERE post_type = %s AND post_title = %s LIMIT 1", 'gift_card', $giftcardno);
                $wooGiftCards = $wpdb->get_results($query);

                // If we found the card in the database (by counting it) and its 
                if (count($wooGiftCards) == 1) {

                    $this->log("Giftcard $giftcardno was found in WooCommerce");

                    // Get the values of giftcard
                    //$balance = floatval(get_post_meta($wooGiftCards["0"]->ID, "_ywgc_amount_total", true)) ?? 0;  // This is initial balance
                    $wooRemaning = floatval(get_post_meta($wooGiftCards["0"]->ID, "_ywgc_balance_total", true)) ?? 0; // This is remaining

                    $this->log("Amount in WooCommerce: $wooRemaning and POS amount: $amount");
                    // Match values to make sure this isnt an outdated card.
                    if ($wooRemaning != $amount) {

                        // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                        // If the card in WooCommerce have been used more then the one in POS. Then update POS.
                        if ($wooRemaning < $amount) {

                            // If WooCommerce gift card have more spent on it, then we need to update POS.
                            $giftcard = [
                                'amount' => (float)$wooRemaning
                            ];

                            // Update giftcard in POS.
                            $this->call("PATCH", "/giftcards/" . $id, $giftcard);
                            continue;

                        } else {

                            // POS. have most spent, then update WooCommerce
                            update_post_meta($wooGiftCards["0"]->ID, "_ywgc_balance_total", $amount); // This is remaining
                            update_post_meta($wooGiftCards["0"]->ID, "_woopos_balance_total", $amount); // Not yet in use. used in future for our own giftcards
                            continue;

                        }
                    }

                    // Giftcard wasnt found in WooCommerce      
                } else {

                    $this->log("Giftcard $giftcardno was NOT found in WooCommerce");

                    // It wasnt found at WooCommerce.
                    // The card wasnt found in WooCommerce, we need to create it.
                    // Create post object
                    $my_post = array(
                        'post_title'    => wp_strip_all_tags($giftcardno),
                        'post_content'  => "",
                        'post_status'   => 'publish',
                        'post_author'   => 1,
                        'post_type'     => "gift_card"
                    );

                    // Insert the post into the database
                    $postID = wp_insert_post($my_post);

                    
                    $this->log("Giftcard $giftcardno created in WooCommerce with amount $amount");

                    // YITH meta update
                    update_post_meta($postID, "_ywgc_amount_total", $amount);  // The gift card amount
                    update_post_meta($postID, "_ywgc_balance_total", $amount); // The current amount available for the customer

                    // WooPos meta update
                    update_post_meta($postID, "_woopos_amount_total", $amount);  // The gift card amount
                    update_post_meta($postID, "_woopos_balance_total", $amount); // The current amount available for the customer

                    continue;
                }
            }

            // If hasMore is false or we have 1000 pages (since it sohuld not happen), break
            if ($giftcards["hasMore"] == false || $page >= 1000) {
                break;
            }

            $page++;
        }
    }
}

// Include callAPI
$wcioWGCSSP = new wcioWGCSSP();
//$woopos_pos = $wcioWGCSSP->woopos_pos;


//include(dirname(__FILE__) . "/includes/callApi.php");

//$wcioWGCSSPservice = new wcioWGCSSPservice();