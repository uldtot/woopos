<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
      exit;
}


// Extend the class with our serviceclass
class wcioWGCSSPservice extends wcioWGCSSP
{


      public $pagelength;
      public $codepattern;

      public function __construct()
      {

            $this->pagelength = get_option('woopos_woopos_pagelength'); // pageLength setting
            $this->codepattern = "************";

            // Run this code if the gift card plugin is Woo Gift Card
            add_action('woopos_cron_sync_woo_pos', array($this, 'woopos_cron_sync_woo_pos')); // Sync from Woo to POS
            add_action('woopos_cron_sync_pos_woo', array($this, 'woopos_cron_sync_pos_woo')); // Sync from POS to Woo
            add_action('admin_init',  array($this, 'ywgc_code_pattern')); // Sets the Giftcard pattern

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
            $giftCardTable = $wpdb->prefix."posts"; // Table for giftcards

            // If its less than 5 minutes ago since last action, then dont? allow this ro run again.
            $woopos_last_action = get_option('woopos_last_action');
            if ($woopos_last_action > (time() - 300)) {
                  return;
            }

            // Update last action
            update_option('woopos_last_action', time());

            // Get Gift cards from database
            $query = $wpdb->prepare("SELECT * FROM $giftCardTable WHERE post_type = %s ORDER BY ID DESC", 'gift_card');
            $wooGiftCards = $wpdb->get_results($query);

            // Get POS giftcards
            // Sets the amount of gift cards per page.
            $pageLength = $this->pagelength ?? 100; // Defaults to a low number to mkae sure it works with most POS.

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
                  $POSGiftcards = array_merge($POSGiftcards, $queryGiftcards["content"]);

                  // If hasMore is false or there is no giftcards, break
                  if ($queryGiftcards["hasMore"] == false || $page >= 1000) {
                        break;
                  }

                  $page++;

            }

            // Loop all WooCommerce giftcards then in each giftcard we loop POS. to find it.
            foreach ($wooGiftCards as $card) {

                  // Update last action
                  update_option('woopos_last_action', time());

                  $giftcardno = $card->post_title; // YITH
                  $balance = floatval(get_post_meta($card->ID, "_ywgc_amount_total", true));  // This is initial balance
                  $remaining = floatval(get_post_meta($card->ID, "_ywgc_balance_total", true)); // This is remaining

                  // Validate giftcardno
                  if (!checkFormat($giftcardno, $this->codepattern)) {
                        continue;
                  } 

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
                              $POSAmountRemaining = floatval($giftcard["amount"]) - floatval($giftcard["amountspent"]); // Full amount minus amount spent gives remaining

                              // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                              // If the card in WooCommerce have been used more then the one in POS., then update POS.
                              if ($remaining < $POSAmountRemaining) {

                                    // If WooCommerce gift card ave more spent on it, then we need to update POS.
                                    // Now updat the amount spent.
                                    $POSAmountSpent = floatval($giftcard["amount"]) - $remaining; // Full amount minus the remaining from wooCommerce gives the amount spent
                                    $giftcardData = [
                                          'amount' => (float)$POSAmountSpent
                                    ];

                                    if ($remaining != $POSAmountRemaining) {

                                          // Update giftcard in POS.
                                          $updatePOSGiftcard = $this->call("PATCH", "/giftcards/" . $giftcard["id"] . "", ['content' => $giftcardData]);

                                        continue;
                                    } else {

                                        continue;

                                    }
                              }
                        }

                  } else if ($searchGiftCardCount == 0) { // IF card wasnt found in POS.

                        // It wasnt dead, now create it in POS. since its not there. 
                        // First check what the balance auctually is.
                        if ($balance == 0) {

                              // The balance was 0, most likely due to the card wasnt created with a balance, then we need to use remaining as balance and fix the card.
                              $giftcardAmount = $remaining;

                        } else {

                              $giftcardAmount = $balance;

                        }

                        // Send to API
                        $giftcard = [
                              "giftcardno" => $giftcardno,
                              "amount" => (float)$giftcardAmount,
                        ];

                        $createPOSGiftcard = $this->call("POST", "/giftcards",  ['content' => $giftcard]);
                        continue;
                  }
            }

           
      }

      // Tjekker POS. gift cards og opretter dem i WooCommerce Gift Cards hvis de ikke allerede findes. Hvis de findes i WooCommerce Gift Cards gør den ikke mere
      // THis function does ONLY check POS., not WooCommerce.
      function woopos_cron_sync_pos_woo()
      {

            // Start run
            // If its less than 5 minutes ago since last action, then dont? allow this ro run again.
            $woopos_last_action_2 = get_option('woopos_last_action_2');
           
            // THis function should check service POS and do the sme as the Woo function did.
            global $wpdb;
            $giftCardTable = $wpdb->prefix."posts";

            // Sets the amount of gift cards per page.
            $pageLength = $this->pagelength ?? 100; // Defaults to a low number to mkae sure it works with most POS.

            // Make the full list of giftcards from POS.
            $POSGiftcards = array();
            $page = 0;
            while (true) {

                  // Now we do the query with the paging.
                  $pageStart = $pageLength * $page;

                  $query = array("pageLength" => $pageLength, "pageStart" => $pageStart); // Start from page 1 (0)
                  $giftcards = $this->call("GET", "/giftcards", $query);

                 
                  // Loops all POS. giftcard
                  foreach ($giftcards["content"] as $card) {

                        // Validate giftcardno
                        if (!checkFormat($card["giftcardno"], $this->codepattern)) {
                              continue;
                        } 

                        // Update last action
                        update_option('woopos_last_action_2', time());

                        $id = $card["id"]; //47021
                        $giftcardno = $card["giftcardno"]; //724503989151
                        $amount = floatval($card["amount"]); //49
                        $amountspent = floatval($card["amountspent"]); //0

                        $amountremaining = $amount - $amountspent; //0
     
                        // Make woo data format of giftcard and search for the giftcard
                        $query = $wpdb->prepare("SELECT * FROM $giftCardTable WHERE post_type = %s AND post_title = %s LIMIT 1", 'gift_card', $giftcardno);
                        $wooGiftCards = $wpdb->get_results($query);

                        // If we found the card in the database (by counting it) and its 
                        if (count($wooGiftCards) == 1) {

                              $balance = floatval(get_post_meta($wooGiftCards["0"]->ID, "_ywgc_amount_total", true)) ?? 0;  // This is initial balance
                              $remaining = floatval(get_post_meta($wooGiftCards["0"]->ID, "_ywgc_balance_total", true)) ?? 0; // This is remaining

                              $spent = $balance - $remaining; // This is spent

                              // Match values to make sure this isnt an outdated card.
                              $wooRemaning = $remaining;

                              if ($wooRemaning != $amountremaining) {

                                    // The amounts wasnt the same, and they should be. Find the card with most spent and update the other.
                                    // If the card in WooCommerce have been used more then the one in POS., then update POS.
                                    if ($wooRemaning < $amountremaining) {

                                          // If WooCommerce gift card have more spent on it, then we need to update POS.
                                          $newAmount = $amount - $wooRemaning;
                                          $giftcard = [
                                                'amount' => (float)$newAmount
                                          ];

                                          // Update giftcard in POS.
                                          $this->call("PATCH", "/giftcards/" . $id, ['content' => $giftcard]);
                                          continue;

                                    } else {

                                          // POS. have most spent, then update WooCommerce
                                          $remaining = $amountremaining;
                                          update_post_meta($wooGiftCards["0"]->ID, "_ywgc_balance_total", $remaining); // This is remaining
                                          update_post_meta($wooGiftCards["0"]->ID, "_woopos_balance_total", $remaining); // Not yet in use. used in future for our own giftcards

                                          continue;

                                    }
                              }
                            
                              // Giftcard wasnt found in WooCommerce      
                        } else {
                           
                              // Skip if its zero, we dont want empty cards in the system.
                              if ($amountremaining == 0) {
                                    continue;
                              }
                         
                              // It wasnt found at WooCommerce.
                              // The card wasnt found in WooCommerce, we need to create it.
                              $time = time();

                              $newWooGiftCardRemaning = $amountremaining;

                       
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

                              // YITH meta update
                              update_post_meta($postID, "_ywgc_amount_total", $amount);  // The gift card amount
                              update_post_meta($postID, "_ywgc_balance_total", $newWooGiftCardRemaning); // The current amount available for the customer
                            
                              // WooPos meta update
                              update_post_meta($postID, "_woopos_amount_total", $amount);  // The gift card amount
                              update_post_meta($postID, "_woopos_balance_total", $newWooGiftCardRemaning); // The current amount available for the customer

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
?>
