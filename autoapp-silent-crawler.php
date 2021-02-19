<?php 
/*
Plugin Name: AutoApp Silent Crawler
URI: autoapp.do
Description: Data Crawler for AutoApp
Author: henrisusanto 
Version: 1.0
Author URI: https://github.com/henrisusanto/autoapp-silent-crawler
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

function autoapp_silent_crawler_shortcode () {
	ob_start ();
	if ( isset( $_POST['autoapp_silent_crawler_submit'] ) ) autoapp_silent_crawler_form_submission_handler ();
	autoapp_silent_crawler_form ();
	return ob_get_clean();
}

add_shortcode( 'autoapp_silent_crawler_url_submit_form', 'autoapp_silent_crawler_shortcode' );

function autoapp_silent_crawler_form () {
    echo '<form action="' . esc_url( $_SERVER['REQUEST_URI'] ) . '" method="POST" enctype="multipart/form-data">';
    echo "
        <p>
            <label>URL to crawl</label>
            <input type=\"text\" name=\"autoapp_silent_crawler_url\" size=\"40\" />
        </p>
    ";
    echo "<input type=\"submit\" value=\"Crawl URL\" name=\"autoapp_silent_crawler_submit\" >";
    echo '</form>';
}

function autoapp_silent_crawler_form_submission_handler () {
    $url = $_POST['autoapp_silent_crawler_url'];
    $user_id = get_current_user_id ();
    $current_path = __DIR__ ;

    $mamp_dir = explode ('/htdocs', $current_path)[0];
    $php_path = "{$mamp_dir}/bin/php/php7.3.8/bin/";
    // $php_path = ''; uncomment for production

    exec ("{$php_path}php {$current_path}/supercarros.php {$url} {$user_id} >/dev/null &");
}

?>