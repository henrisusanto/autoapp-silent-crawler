<?php

$url = $argv[1];
$user_id = $argv[2];
$current_path = __DIR__ ;
$site_dir = explode ('/wp-content', $current_path)[0];
$wp_load = "{$site_dir}/wp-load.php";
require_once ($wp_load);
supercarros_start ($url, $user_id);

function supercarros_debug ($txt) {
    $myfile = fopen (__DIR__ . '/supercarros-log.html', 'a');
    fwrite($myfile, "\n". $txt);
    fclose($myfile);
}

function supercarros_reset ($user_id) {
    $query = new WP_Query ();
    $posts = $query->query (array(
        'post_type' => 'listings',
        'post_status' => 'published',
        'post_author' => $user_id,
        'posts_per_page' => -1
    ));
    wp_reset_postdata();
    foreach ($posts as $post) {
        $photos = $query->query (array(
            'post_type' => 'attachment',
            'post_status' => 'any',
            'post_parent' => $post->ID,
            'posts_per_page' => -1
        ));
        wp_reset_postdata();
        foreach ($photos as $photo) {
            wp_delete_attachment ($photo->ID, true);
        }
        wp_delete_post ($post->ID, true);
    }
}

function supercarros_start ($url, $user_id) {
    $dealer_first_page_dom = supercarros_get_dom ($url);
    $cars_url = array_map (function ($url) {
        return 'https://www.supercarros.com' . $url;
    }, supercarros_get_cars_url ($dealer_first_page_dom));

    foreach ($cars_url as $url) supercarros_car_url_to_listing ($url, $user_id);
    $dealer_pages_url = array_map (function ($url) {
        return 'https://www.supercarros.com' . $url;
    }, supercarros_get_dealer_pages_url ($dealer_first_page_dom));

    foreach ($dealer_pages_url as $dp_url) {
        $dealer_page_dom = supercarros_get_dom ($dp_url);
        $cars_url = array_map (function ($url) {
            return 'https://www.supercarros.com' . $url;
        }, supercarros_get_cars_url ($dealer_page_dom));
        foreach ($cars_url as $url) {
            supercarros_car_url_to_listing ($url, $user_id);
        }
    }   
}

function supercarros_get_xpath ($name) {
    $xpath = array (
        'page_numbers' => '/html/body/div[3]/div/div[2]/div[1]/div/div[3]/div[4]/div[1]/ul/li/a',
        'cars' => '/html/body/div[3]/div/div[2]/div[1]/div/div[3]/ul/li/a',

        'car_name' => '/html/body/div[3]/div/div[2]/div[1]/div[1]/h1',
        'car_price'=> '/html/body/div[3]/div/div[2]/div[1]/div[1]/h3',
        'car_address' => '/html/body/div[3]/div/div[2]/div[2]/ul/li[7]',
        'car_body' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[5]/table/tr[3]/td[4]',
        'car_mileage' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[5]/table/tr[4]/td[4]',
        'car_fuel' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[5]/table/tr[5]/td[2]',
        'car_engine' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[5]/table/tr[2]/td[2]',
        'car_transmission' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[5]/table/tr[6]/td[2]',
        'car_drive' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[5]/table/tr[7]/td[2]',
        'car_exterior-color' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[5]/table/tr[3]/td[2]',
        'car_interior-color' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[5]/table/tr[4]/td[2]',

        'car_photos' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[1]/ul/li/a',
        'car_gmap' => '/html/body/div[3]/div/div[2]/div[2]/ul/li/iframe',
        'car_features' => '/html/body/div[3]/div/div[2]/div[1]/div[2]/div[2]/div[6]/ul/li'
    );
    return $xpath[$name];
}

function supercarros_get_dom ($url) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "Cookie: __cfduid=d19e8c8a1677ad2d060c3143db6a40bda1592099097"
      ),
    ));
    
    $response = curl_exec($curl);

    /*
      create a file named page.html, and
      uncomment following lines  to print HTML result to a file,
      in case the site has different page to serve when accessed through CURL instead of regular web browser
      we can get xpath from it then
      $file = plugin_dir_path( __FILE__ ) . 'page.html';
      file_put_contents($file, $response);
    */

    curl_close($curl);

    $dom = new DOMDocument();
    @$dom->loadHTML($response);
    return new DomXpath($dom);
}

function supercarros_get_dealer_pages_url ($dealer_first_page_dom) {
    $xpath = supercarros_get_xpath ('page_numbers');
    $pages = array ();
    foreach ($dealer_first_page_dom->query($xpath) as $index => $page_link) {
        if (0 === $index) continue;
        if ('»' === $page_link->nodeValue) continue;
        $href = $page_link->getAttribute ('href');
        $pages[] = $href;        
    }
    return $pages;
}

function supercarros_get_cars_url ($dealer_page_dom) {
    $xpath = supercarros_get_xpath ('cars');
    $cars = array ();
    foreach ($dealer_page_dom->query($xpath) as $index => $link) {
        $url = $link->getAttribute('href');
        $cars[] = $url;
    }
    return $cars;
}

function supercarros_car_url_to_listing ($car_page_url, $user_id) {
    $dom = supercarros_get_dom ($car_page_url);
    $car = supercarros_get_attributes ($dom);
    supercarros_create_listing ($car, $user_id);
}

function supercarros_get_attributes ($car_page_dom) {
    $car = array ();
    $attributes = array (
        'car_name',
        'car_price',
        'car_address',
        'car_body',
        'car_mileage',
        'car_fuel',
        'car_engine',
        'car_transmission',
        'car_drive',
        'car_exterior-color',
        'car_interior-color'
    );
    foreach ($attributes as $field) {
        $xpath = supercarros_get_xpath ($field);
        $car[$field] = $car_page_dom->query($xpath)->item(0)->nodeValue;
    }

    $car['car_currency'] = substr($car['car_price'], 0, 3);

    preg_match_all('!\d+!', $car['car_price'], $matches);
    $car['car_price'] = implode ('', $matches[0]);

    $car['car_photos'] = array ();
    $xpath = supercarros_get_xpath ('car_photos');
    foreach ($car_page_dom->query($xpath) as $node) {
        $car['car_photos'][] = $node->getAttribute('href');
    }

    $xpath = supercarros_get_xpath ('car_gmap');
    $gmap_dom = $car_page_dom->query($xpath);
    if ($gmap_dom->count() > 0) {
        $src = $gmap_dom->item(0)->getAttribute('src');
        $latLng = explode ('&zoom', explode ('er=', $src)[1])[0];
        $car['car_lat'] = trim (explode (',', $latLng)[0]);
        $car['car_lng'] = trim (explode (',', $latLng)[1]);
    }

    $car['car_features'] = array ();
    $xpath = supercarros_get_xpath ('car_features');
    foreach ($car_page_dom->query($xpath) as $node) {
        if ('display:none;' === $node->getAttribute ('style')) continue;
        $car['car_features'][] = $node->nodeValue;
    }

    return $car;
}

function supercarros_create_listing ($car, $user_id) {
    $my_post = array(
        'post_title'    => wp_strip_all_tags($car['car_name']),
        'post_content'  => '[vc_row][vc_column width="2/3" offset="vc_hidden-xs"][vc_row_inner el_class="titulo2"][vc_column_inner width="1/2"][stm_single_car_title][/vc_column_inner][vc_column_inner width="1/2"][stm_single_car_price][/vc_column_inner][/vc_row_inner][vc_row_inner][vc_column_inner offset="vc_hidden-xs"][stm_single_car_actions][stm_single_car_gallery][vc_column_text]
<h4 class="regular size-h4">[bsa_pro_ad_space id=11]</h4>
[/vc_column_text][vc_column_text]
<h4 class="regular size-h4">Ficha técnica</h4>
[/vc_column_text][stm_single_car_data][vc_column_text]
<h4 class="regular size-h4">Equipamiento</h4>
[/vc_column_text][stm_car_listing_features][/vc_column_inner][/vc_row_inner][vc_row_inner el_class="rowcalc2"][vc_column_inner el_class="calculadora1" width="1/2"][stm_single_car_calculator][vc_column_text]
<div class="botonf">
<div class="botonf">[popup_anything id="6460"]</div>
</div>
[/vc_column_text][/vc_column_inner][vc_column_inner width="1/2" el_id="seguro"][vc_column_text]
<p style="text-align: center;">Proximamante</p>
<p style="text-align: center;">Calculadora de Seguros</p>
[/vc_column_text][vc_single_image image="4970" img_size="full" alignment="center"][/vc_column_inner][/vc_row_inner][/vc_column][vc_column width="1/3" el_class="sidebar" offset="vc_hidden-xs"][stm_car_dealer_info][vc_column_text][xyz-ips snippet="telefono"][/vc_column_text][vc_tta_accordion active_section="0" collapsible_all="true"][vc_tta_section i_position="right" i_icon_fontawesome="fas fa-user-tie" add_icon="true" title="QUIERO QUE ME CONTACTEN" tab_id="1608131086889-f1bc9f81-f65b59b2-e9a120f0-c87c"][stm_car_listing_contact_form form="1665"][/vc_tta_section][/vc_tta_accordion][stm_gmap map_zoom="18" image="9029"][vc_column_text]
<h4 class="regular size-h4">[bsa_pro_ad_space id=12]</h4>
[/vc_column_text][vc_column_text]
<h4 class="regular size-h4">[bsa_pro_ad_space id=13]</h4>
[/vc_column_text][/vc_column][/vc_row][vc_row el_class="similar"][vc_column offset="vc_hidden-xs" el_class="similar2"][vc_column_text]
<h3 class="regular">Vehículos que te pueden interesar</h3>
[/vc_column_text][stm_car_listing_similar][vc_column_text]
<h4 class="regular size-h4">[bsa_pro_ad_space id=14]</h4>
[/vc_column_text][/vc_column][/vc_row][vc_row][vc_column offset="vc_hidden-lg vc_hidden-md vc_hidden-sm"][stm_single_car_title][stm_single_car_price][stm_single_car_actions][vc_row_inner el_class="rensposivesingle"][vc_column_inner width="1/2"][stm_single_car_gallery][stm_car_dealer_info][/vc_column_inner][/vc_row_inner][/vc_column][/vc_row][vc_row][vc_column offset="vc_hidden-lg vc_hidden-md vc_hidden-sm"][vc_column_text]
<h4 class="regular size-h4">[bsa_pro_ad_space id=11]</h4>
[/vc_column_text][vc_column_text]
<h4 class="regular size-h4">Ficha técnica</h4>
[/vc_column_text][stm_single_car_data][vc_column_text]
<h4 class="regular size-h4">Equipamiento</h4>
[/vc_column_text][stm_car_listing_features][/vc_column][/vc_row][vc_row][vc_column offset="vc_hidden-lg vc_hidden-md vc_hidden-sm"][vc_column_text]
<h3 class="regular">Vehículos que te pueden interesar</h3>
[/vc_column_text][stm_car_listing_similar][/vc_column][/vc_row][vc_row][vc_column][vc_column_inner offset="vc_hidden-lg vc_hidden-md vc_hidden-sm"][stm_single_car_calculator][vc_column_text]
<h4 class="regular size-h4">[bsa_pro_ad_space id=14]</h4>
[/vc_column_text][/vc_column_inner][/vc_column][/vc_row]',
        'post_status'   => 'publish',
        'post_type' => 'listings',
        'post_author' => $user_id
    );
	$post_id = wp_insert_post ($my_post);
    add_post_meta ($post_id, '_wpb_vc_js_status', true); 

	// free text attributes
	$attributes = array ('car_name', 'car_price', 'car_mileage', 'car_engine');
	foreach ($attributes as $field) {
		add_post_meta ($post_id, str_replace ('car_', '', $field), $car[$field]);
	}

	// attribute with defined options
	$taxonomies = array ('car_body', 'car_fuel', 'car_transmission', 'car_drive', 'car_exterior-color', 'car_interior-color');
	foreach ($taxonomies as $field) {
		$term_name = $car[$field];
		$taxonomy = str_replace ('car_', '', $field);

		if (!term_exists ($term_name, $taxonomy)) wp_insert_term ($term_name, $taxonomy);
		$term = get_term_by ('name', $term_name, $taxonomy);
		$slug = $term->slug;
		add_post_meta ($post_id, $taxonomy, $slug);
	}

    // currency
	$term_name = $car['car_currency'];
	$taxonomy = 'moneda';
	if (!term_exists ($term_name, $taxonomy)) wp_insert_term ($term_name, $taxonomy);
	$term = get_term_by ('name', $term_name, $taxonomy);
	$slug = $term->slug;
	add_post_meta ($post_id, $taxonomy, $slug);

	// address, lat, & lng
	add_post_meta( $post_id, 'stm_car_location', $car['car_address']);
	add_post_meta( $post_id, 'stm_lat_car_admin', $car['car_lat']);
	add_post_meta( $post_id, 'stm_lng_car_admin', $car['car_lng']);

    // createdBy
    add_post_meta( $post_id, 'stm_car_user', $user_id);

    // MAKE (marca), MODEL(modelo, serie) & YEAR(ca-year)
    $name = $car['car_name'];
    $year = substr($name, -4);
    $make = explode(' ', $name)[0];
    $serie= str_replace($year, '', str_replace($make, '', $name));
    $taxonomies = array (
        'ca-year' => $year,
        'make' => $make,
        'serie' => $serie
    );
    foreach($taxonomies as $taxonomy => $term_name)
    {
        if (!term_exists ($term_name, $taxonomy)) wp_insert_term ($term_name, $taxonomy);
        $term = get_term_by ('name', $term_name, $taxonomy);
        $slug = $term->slug;
        add_post_meta ($post_id, $taxonomy, $slug);
    }

	// additional features
	foreach ($car['car_features'] as $feature) {
		$term_name = $feature;
        $taxonomy = 'stm_additional_features';

		if (!term_exists ($term_name, $taxonomy)) {
            wp_insert_term ($term_name, $taxonomy);
        }
		add_post_meta ($post_id, 'additional_features', implode (',', $car['car_features']));
	}

	// photos
	$photoIDs = array ();
    foreach ($car['car_photos'] as $src) {
        $photoIDs[] = supercarros_insert_attachment_from_url ($src, $post_id);
	}
	add_post_meta ($post_id, 'gallery', $photoIDs); 
    set_post_thumbnail($post_id, $photoIDs[0]);
}

function supercarros_insert_attachment_from_url ($url, $parent_post_id = null) {

	if ( !class_exists( 'WP_Http' ) )
		include_once( ABSPATH . WPINC . '/class-http.php' );

    $http = new WP_Http();
    $response = $http->request ($url);
	if ( $response['errors']) {
		return false;
	}

	$upload = wp_upload_bits( basename($url), null, $response['body'] );
	if ( !empty( $upload['error'] ) ) {
		return false;
	}

	$file_path = $upload['file'];
	$file_name = basename( $file_path );
	$file_type = wp_check_filetype( $file_name, null );
	$attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
	$wp_upload_dir = wp_upload_dir();

	$post_info = array(
		'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
		'post_mime_type' => $file_type['type'],
		'post_title'     => $attachment_title,
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	// Create the attachment
	$attach_id = wp_insert_attachment( $post_info, $file_path, $parent_post_id );

	// Include image.php
	require_once( ABSPATH . 'wp-admin/includes/image.php' );

	// Define attachment metadata
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );

	// Assign metadata to attachment
	wp_update_attachment_metadata( $attach_id,  $attach_data );

	return $attach_id;
}

?>