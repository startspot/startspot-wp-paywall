<?php
/*
Plugin Name: Startspot-WP-Paywall
Description: Places a paywall after every more-tag into the user's post(s).
Version: 0.1
Author: Jürgen Scholz
Author URI: https://startspot.org
*/


/**
 * Tell WordPress to use "/lang/" as our language folder
 */
function startspot_load_textdomain() {
    load_plugin_textdomain( "startspot-wp-paywall", false, dirname( plugin_basename(__FILE__) ) . "/lang/" );
}
add_action("plugins_loaded", "startspot_load_textdomain");


/**
 * Store default values for the option values, which the user is able to change in the admin menu
 */
function startspot_wp_paywall_activation() {
    add_option( "startspot_wp_paywall_withdrawal_address", "KMA7vqobAMtkradkqfr32aC59SMUyycxhE", "", "yes" );
    add_option( "startspot_wp_paywall_deposit_factor", "1000000", "", "yes" );
}
register_activation_hook( __FILE__, "startspot_wp_paywall_activation" );


/**
 * Delete the option values that this plugin is using, after the plugin is deactivated
 */
function startspot_wp_paywall_deactivation() {
    delete_option( "startspot_wp_paywall_withdrawal_address" );
    delete_option( "startspot_wp_paywall_deposit_factor" );
}
register_deactivation_hook( __FILE__, "startspot_wp_paywall_deactivation" );


/**
 * Place a paywall after every <code><!--more--></code> into the user's post(s).
 */
function startspot_wp_paywall( $content ) {

    if ( is_singular( "post" ) ) {

        global $more;
        $more = 0;
        $content_cut = get_the_content("");
        $more = 1;
        $content_full = get_the_content();

        if ($content_cut != $content_full) {

            $postdata = [
                "withdrawal_network"    => "kenfmcoin",
                "withdrawal_address"    => get_option( "startspot_wp_paywall_withdrawal_address" ),
                "deposit_uid"           => get_permalink(),
                "deposit_network"       => "kenfmcoin",
                "deposit_factor"        => get_option( "startspot_wp_paywall_deposit_factor" )
            ];

            $opts = [
                "http" => [
                    "method"    => "POST",
                    "header"    => "Content-type: application/json",
                    "content"   => json_encode($postdata)
                ],
                "ssl" => [
                    "verify_peer"           => true,
                    //"cafile"                => "/etc/ssl/certs/ca-certificates.crt",
                    "peer_name"             => "api.startspot.org",
                    "ciphers"               => "HIGH:!SSLv2:!SSLv3",
                    "disable_compression"   => true
                ]
            ];

            $context = stream_context_create($opts);
            $url     = "https://api.startspot.org:443/v0.11/public/connect";
            $data    = @file_get_contents($url, false, $context);
            $php     = json_decode($data);
            if (isset($php)) {
                if (isset($php->success) && $php->success === true) {
                    if (isset($php->result)) {
                        if (isset($php->result->DepositEndDate)) {
                            $now = date("d.m.Y H:i:s T");
                            $until = date("d.m.Y H:i:s T", strToTime($php->result->DepositEndDate));
                            if ($now < $until) {
                                return $content;
                            }
                        }
                        $paywall  = "<h1>".__( "Paywall", "startspot-wp-paywall")."</h1><hr>";
                        $paywall .= "<p><a href=\"kenfmcoin:".$php->result->DepositAddress."?amount=".((3600/$php->result->DepositFactor))."\"><img src=\"https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".$php->result->DepositAddress."&choe=UTF-8\" alt=\"Scan\"></a></p>";
                        $paywall .= "<p>".__( "Deposit Address", "startspot-wp-paywall").": ".$php->result->DepositAddress." (<a href=\"https://explorer.kenfmcoin.org/address/".$php->result->DepositAddress."\">".__( "open address in explorer", "startspot-wp-paywall")."</a>)</p>";
                        $paywall .= "<p>".__( "Costs/Hour", "startspot-wp-paywall").": ".((3600/$php->result->DepositFactor))." Kenfmcoin (<a href=\"https://kenfmcoin.org\">".__( "learn more", "startspot-wp-paywall")."</a>)</p>";
                        $paywall .= "<p><a href=\"".get_permalink()."\">".__( "Paywall", "startspot-wp-paywall")." ".__( "refresh", "startspot-wp-paywall")."</a></p>";
                        return $content_cut.$paywall;
                    }
                }
            }
        }
    }
    return $content;
}

add_filter( "the_content", "startspot_wp_paywall", 1 );


/**
 * Tell WordPress to add an options page for the administration menu.
 */
function startspot_wp_paywall_admin_menu() {
    add_options_page( "Startspot-WP-Paywall Admin", "Startspot-WP-Paywall", "manage_options", "startspot-wp-paywall-admin.php", "startspot_wp_paywall_admin_page");
}


/**
 * The administration menu.
 */
function startspot_wp_paywall_admin_page() {
    if( isset($_POST) ) {
        if( isset( $_POST[ "withdrawal_address" ] ) ) {
            if( preg_match("/^[K][a-km-zA-HJ-NP-Z1-9]{26,33}$/", $_POST[ "withdrawal_address" ]) === 1 ) {
                update_option( "startspot_wp_paywall_withdrawal_address", $_POST[ "withdrawal_address" ], "yes" );
            }
        }
        if( isset( $_POST[ "deposit_factor" ] ) ) {
            if( 0 < intval($_POST[ "deposit_factor" ]) && intval($_POST[ "deposit_factor" ]) < 5000000000 ) {
                update_option( "startspot_wp_paywall_deposit_factor", $_POST[ "deposit_factor" ], "yes" );
            }
        }
    }
    $html = "<form action=\"options-general.php?page=startspot-wp-paywall-admin.php\" method=\"post\">";
    $html.= "<div class=\"wrap\">";
    $html.= "<h1>Startspot-WP-Paywall</h1>";
    $html.= "<table class=\"form-table\"><tr><th scope=\"row\">".__( "Kenfmcoin address", "startspot-wp-paywall")."</th><td><input type=\"text\" class=\"regular-text code\" name=\"withdrawal_address\" id=\"withdrawal_address\" placeholder=\"".get_option( "startspot_wp_paywall_withdrawal_address", "KBL84HSZrbx3h8ixfRCDQanNS5UCbW1Ze4" )."\" value=\"".get_option( "startspot_wp_paywall_withdrawal_address", "" )."\" /></td></tr><tr><th scope=\"row\">".__( "Deposit factor", "startspot-wp-paywall")."</th><td><input type=\"text\" class=\"regular-text code\" name=\"deposit_factor\" id=\"deposit_factor\" placeholder=\"".get_option( "startspot_wp_paywall_deposit_factor", "1000000" )."\" value=\"".get_option( "startspot_wp_paywall_deposit_factor", "" )."\" /></td></tr></table>";
    $html.= "<p class=\"submit\"><input type=\"submit\" name=\"submit\" id=\"submit\" class=\"button button-primary\" value=\"".__( "Save changes", "startspot-wp-paywall")."\"  /></p>";
    $html.= "</div>";
    $html.= "</form>";
    echo $html;
}

add_action( "admin_menu", "startspot_wp_paywall_admin_menu" );

?>
