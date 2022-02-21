<?php

namespace OpenChargeAPI\Sites;

class Open_Charge_API
{

    const API_KEY = 'de84fceb-fbc6-423d-912f-0b5e4dd44d56';

    public static $instance;

    public static function init()
    {
        null === self::$instance && self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        \add_action( 'init', [ $this, 'register_open_charge_poi_cpt' ], 1 );
        \add_action( 'wp_ajax_nopriv_get_poi_sites_from_api', [ $this, 'get_poi_sites_from_api' ], 1);
        \add_action( 'wp_ajax_get_poi_sites_from_api', [ $this, 'get_poi_sites_from_api' ], 1);
        \add_action( 'wp_ajax_nopriv_delete_poi_sites_from_db', [ $this, 'delete_poi_sites_from_db' ], 1);
        \add_action( 'wp_ajax_delete_poi_sites_from_db', [ $this, 'delete_poi_sites_from_db' ], 1);
    }

    public function register_open_charge_poi_cpt() 
    {

        \register_post_type('opencharge', [
            'label' => 'Open Charge POIs',
            'public' => true,
            'capability_type' => 'post'
        ]);
        
    }

    public function delete_poi_sites_from_db() 
    {
  
        global $wpdb;
      
        $wpdb->query("DELETE FROM wp_posts WHERE post_type='opencharge'");
        $wpdb->query("DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT id FROM wp_posts);");
        $wpdb->query("DELETE FROM wp_term_relationships WHERE object_id NOT IN (SELECT id FROM wp_posts)");
      
    }

    public function get_poi_sites_from_api()
    {

        // $file = plugin_dir_url( __FILE__ ) . 'importlog.txt';
        $file = __DIR__ . '/../error_log.txt';

        $results = [];

        //https://api.openchargemap.io/v3/poi?key=de84fceb-fbc6-423d-912f-0b5e4dd44d56&countrycode=US&maxresults=2&verbose=false&opendata=true&chargepointid=100001

        $results = \wp_remote_retrieve_body( \wp_remote_get('https://api.openchargemap.io/v3/poi?key=' . self::API_KEY . '&countrycode=US&maxresults=3&verbose=false&opendata=true') );
        
        file_put_contents( $file, "Imported Item" . FILE_APPEND );

        $results = json_decode( $results, true );

        if( ! is_array( $results ) || empty( $results ) ) {
            return false;
        }

        $poi_sites[] = $results;

        $i = 0;

        foreach( $poi_sites[0] as $poi_site ) {

            //POI Info
            $poi_site_slug = \sanitize_title( $poi_site['AddressInfo']['Title'] . '-' . $poi_site['ID'] );
            $poi_site_last_updated = $poi_site['DateLastStatusUpdate'];


            // POI Address Info
            $poi_site_address_line_one = $poi_site['AddressInfo']['AddressLine1'];
            $poi_site_address_town = $poi_site['AddressInfo']['Town'];
            $poi_site_address_state = $poi_site['AddressInfo']['StateOrProvince'];
            $poi_site_address_postcode = $poi_site['AddressInfo']['Postcode'];
            $poi_site_address_long = $poi_site['AddressInfo']['Longitude'];
            $poi_site_address_lat  = $poi_site['AddressInfo']['Latitude'];

            $inserted_poi_site = \wp_insert_post([
                'post_name'   => $poi_site_slug,
                'post_title'  => $poi_site_slug,
                'post_type'   => 'opencharge',
                'post_status' => 'publish'
            ]);

            if( \is_wp_error( $inserted_poi_site ) || $inserted_poi_site === 0 ) {
                error_log( 'Could not insert' . $poi_site_slug );
                continue;
            }

            $datafields = [
                // General Information
                'field_62115949c9aba' => $poi_site['UsageTypeID'],
                'field_62114f67473e8' => $poi_site['UsageCost'],
                'field_62114f7a473e9' => $poi_site_last_updated,
                'field_621151aaa77df' => $poi_site['NumberOfPoints'],
                'field_6211591ab1919' => $poi_site['GeneralComments'],
                // Address
                'field_6211bb85ebd50' => $poi_site_address_line_one,
                'field_6211bb9aebd51' => $poi_site_address_town,
                'field_6211bba6ebd52' => $poi_site_address_state,
                'field_6211bc03ebd53' => $poi_site_address_postcode,
                'field_62100e6afe06d' => $poi_site_address_long,
                'field_62100e72fe06e' => $poi_site_address_lat,
            ];

            foreach( $datafields as $key => $poi_site['ID'] ) {
                \update_field( $key, $poi_site['ID'], $inserted_poi_site );
            }

            if( $i++ == 2 ) break;

        }

        \wp_remote_post( \admin_url('admin-ajax.php?action=get_poi_sites_from_api'), [
            'blocking' => false,
            'sslverify' => false
        ] );

    }

}

Open_Charge_API::init();