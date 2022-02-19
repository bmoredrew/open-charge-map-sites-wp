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
        \add_action( 'init', [ $this, 'register_open_charge_poi_cpt' ], 0 );
        \add_action( 'wp_ajax_nopriv_get_poi_sites_from_api', [ $this, 'get_poi_sites_from_api' ], 1);
        \add_action( 'wp_ajax_get_poi_sites_from_api', [ $this, 'get_poi_sites_from_api' ], 1);
    }

    public function register_open_charge_poi_cpt() 
    {

        register_post_type('opencharge', [
            'label' => 'Open Charge POIs',
            'public' => true,
            'capability_type' => 'post'
        ]);
        
    }

    function delete_poi_sites_from_db() 
    {
  
        global $wpdb;
      
        $wpdb->query("DELETE FROM wp_posts WHERE post_type='opencharge'");
        $wpdb->query("DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT id FROM wp_posts);");
        $wpdb->query("DELETE FROM wp_term_relationships WHERE object_id NOT IN (SELECT id FROM wp_posts)");
      
    }
    // delete_poi_sites_from_db();

    public function get_poi_sites_from_api()
    {

        $file = plugin_dir_url( __FILE__ ) . 'importlog.txt';

        $poi_sites = [];

        //https://api.openchargemap.io/v3/poi?key=de84fceb-fbc6-423d-912f-0b5e4dd44d56&countrycode=US&maxresults=2&verbose=false&opendata=true

        $results = wp_remote_retrieve_body( wp_remote_get('https://api.openchargemap.io/v3/poi?key=' . Open_Charge_API::API_KEY . '&countrycode=US&maxresults=3&verbose=false&opendata=true') );
        
        file_put_contents( $file, "Imported Item" . FILE_APPEND );

        $results = json_decode( $results );

        if( ! is_array( $results ) || empty( $results ) ) {
            return false;
        }

        $poi_sites[] = $results;

        foreach( $poi_sites[0] as $poi_site ) {

            $poi_site_slug = sanitize_title( $poi_site->ID );

            $inserted_poi_site = wp_insert_post([
                'post_name'   => $poi_site_slug,
                'post_title'  => $poi_site_slug,
                'post_type'   => 'opencharge',
                'post_status' => 'publish'
            ]);

            if( is_wp_error( $inserted_poi_site ) || $inserted_poi_site === 0 ) {
                error_log( 'Could not insert' . $poi_site_slug );
                continue;
            }

            $datafields = [
                // 'field_62100e6afe06d' => 'longitude',
                // 'field_62100e72fe06e' => 'latitude',
                'field_62115949c9aba' => 'UsageTypeID',
                'field_62114f67473e8' => 'UsageCost',
                'field_62114f7a473e9' => 'DateLastStatusUpdate',
                'field_621151aaa77df' => 'NumberOfPoints',
                'field_6211591ab1919' => 'GeneralComments'
            ];

            foreach( $datafields as $key => $ID ) {
                update_field( $key, $poi_site->$ID, $inserted_poi_site );
            }

        }

        wp_remote_post( admin_url('admin-ajax.php?action=get_poi_sites_from_api'), [
            'blocking' => false,
            'sslverify' => false
        ] );

    }

}

Open_Charge_API::init();