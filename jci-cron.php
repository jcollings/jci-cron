<?php
/*
Plugin Name: JC Importer - Cron Addon
Plugin URI: http://jamescollings.co.uk/wordpress-plugins/jc-importer/
Description: Set imports to run every x amount of time via cron
Author: James Collings <james@jclabs.co.uk>
Author URI: http://www.jamescollings.co.uk
Version: 0.1
*/

class JCI_Cron{

	var $plugin_dir = false;
	var $plugin_url = false;
	var $version = '0.1';
	private $min_version = '0.2';

	public function __construct(){

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'jci/init', array( $this, 'init' ), 10, 1);	
		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugins_url( '/', __FILE__ );

		add_filter( 'cron_schedules', array($this, 'add_cron_interval' ) ); 
	}

	/**
	 * Run after JC Importer has loaded
	 */
	public function init(){
		
		add_action( 'jci/output_datasource_section' , array( $this, 'output_cron_add_settings' ) );
		add_action( 'jci/importer_setting_section' , array( $this, 'output_cron_edit_settings' ) );

		add_filter( 'jci/setup_forms', array($this, 'setup_forms'), 10, 2 );
		add_filter( 'jci/process_create_form', array($this, 'process_create_form'), 10, 3 );
        add_filter( 'jci/process_edit_form', array($this, 'process_edit_form'), 10, 1 );

		add_action( 'jci_cron_schedule', array( $this, 'setup_crons' ) );  

		if( !wp_next_scheduled( 'jci_cron_schedule' ) ) {
			wp_schedule_event( time(), 'jci_1_minute', 'jci_cron_schedule' );
		}             
	}

	public function install(){

		global $jcimporter;

		if(!version_compare($jcimporter->version, $this->min_version, '>=')){
			echo 'Sorry, JCI Cron requires version JC Importer version '.$this->min_version.' or greater to be installed.';
	        exit;
		}
	}

	/**
	 * Output fields to setup cron
	 * @return void
	 */
	public function output_cron_add_settings(){
		?>
		<div class="hidden show-remote toggle-field">
			<?php
			$this->output_cron_fields();
			?>
		</div>
		<?php
	}

	/**
	 * Output fields to setup cron
	 * @return void
	 */
	public function output_cron_edit_settings(){

		global $jcimporter;

		?>
		<div class="jci-group-cron jci-group-section" data-section-id="cron">
			<div class="cron">
				<h4>Recurring Imports</h4>
				<?php
				if( $jcimporter->importer->get_import_type() == 'remote' ){
					$this->output_cron_fields();
				}
				?>
			</div>
		</div>
		<?php
		
	}

	/**
	 * Output cron fields
	 * @return void
	 */
	private function output_cron_fields(){

		global $jcimporter;

		$cron_enabled = false;
		$cron_minutes = '';
		$msg = '';

		if( isset( $jcimporter->importer ) ){
			$importer_id = $jcimporter->importer->get_ID();
			$cron_enabled_meta = get_post_meta( $importer_id, '_jci_cron_enabled', true);
			$cron_minutes = get_post_meta( $importer_id, '_jci_cron_minutes', true);
			$cron_last_ran = get_post_meta( $importer_id, '_jci_cron_last_ran', true );

			$cron_enabled = $cron_enabled_meta === 'yes' ? true : false;

			if($cron_last_ran > 0){
				$msg = sprintf("<p class=\"cron-msg\">Cron last ran on %s.</p>", date('d/m/Y \a\t H:i:s', $cron_last_ran));
			}

		}

		// echo cron message
		echo $msg;

		echo JCI_FormHelper::checkbox( 'enable_cron', array(
			'label'   => '<strong>Enable Cron</strong> - Set a schedule to run the current import.',
			'default' => 1,
			'checked' => $cron_enabled
		) );
		?>
		<div class="jci-sub-section show-cron">
			<?php
			echo JCI_FormHelper::text( 'cron_minutes', array( 'label' => 'Minutes', 'default' => $cron_minutes ) );
			?>
		</div>
		<?php
	}

	/**
	 * Setup Form validation
	 * @param  array  $forms       
	 * @param  string $import_type 
	 * @return array
	 */
	public function setup_forms($forms = array(), $import_type){

		if($import_type == 'post'){
	        $forms['CreateImporter']['validation']['post_field'] = array(
	            'rule' => array('required'),
	            'message' => 'This Field is required',
	        );
	    }

        return $forms;
	}

	/**
	 * Save Add Importer Settings
	 * @param  array  $general     
	 * @param  string $import_type 
	 * @return array
	 */
	public function process_create_form($general = array(), $import_type, $importer_id){

		$cron_enabled = isset($_POST['jc-importer_enable_cron']) && $_POST['jc-importer_enable_cron'] == 1 ? 'yes' : 'no';
		$cron_minutes = intval($_POST['jc-importer_cron_minutes']);

		update_post_meta( $importer_id, '_jci_cron_enabled', $cron_enabled );
		update_post_meta( $importer_id, '_jci_cron_minutes', $cron_minutes );

		if($cron_enabled === 'yes'){
			add_post_meta( $importer_id, '_jci_cron_last_ran', 0, true );
		}

		return $general;
	}

	/**
	 * Process Cron fields
	 * @param  array  $settings 
	 * @return array
	 */
	public function process_edit_form($settings = array()){

		global $jcimporter;
		$importer_id = $jcimporter->importer->get_ID();

		$cron_enabled = isset($_POST['jc-importer_enable_cron']) && $_POST['jc-importer_enable_cron'] == 1 ? 'yes' : 'no';
		$cron_minutes = intval($_POST['jc-importer_cron_minutes']);

		update_post_meta( $importer_id, '_jci_cron_enabled', $cron_enabled );
		update_post_meta( $importer_id, '_jci_cron_minutes', $cron_minutes );

		if($cron_enabled === 'yes' && get_post_meta( $importer_id, '_jci_cron_last_ran', true ) == false){
			add_post_meta( $importer_id, '_jci_cron_last_ran', 0, true );
		}

		return $settings;
	}

    public function setup_crons(){

    	global $jcimporter;

    	$import = $this->get_scheduled_import();
    	$result = false;

    	if($import){

    		$importer_id = $import->ID;
    		$jcimporter->importer = new JC_Importer_Core( $importer_id );

    		if($jcimporter->importer->get_import_type() == 'remote'){

    			// fetch remote file and attach new one
    			$remote_settings = ImporterModel::getImportSettings( $importer_id, 'remote' );
				$url             = $remote_settings['remote_url'];
				$dest            = basename( $url );
				$attach          = new JC_CURL_Attachments();
				$result          = $attach->attach_remote_file( $importer_id, $url, $dest, array('importer-file' => true) );

				// update settings with new file
				ImporterModel::setImporterMeta( $importer_id, array( '_import_settings', 'import_file' ), $result['id'] );

				// reload importer settings
				ImporterModel::clearImportSettings();
    		}

    		// process import against new file
    		if($result){

    			$import_result = $jcimporter->importer->run_import();
		    	update_post_meta( $importer_id, '_jci_cron_last_ran', time() );
    		}
    		
    	}
    }

    /**
     * Get next post record of importer to run from database
     * @return object
     */
    private function get_scheduled_import(){

    	add_filter('posts_clauses', array( $this, 'posts_clauses'));

    	$query = new WP_Query(array(
    		'post_type' => 'jc-imports',
    		'meta_query' => array(
    			array(
    				'key' => '_jci_cron_enabled',
    				'value' => 'yes'
    			)
    		)
    	));

    	remove_filter('posts_clauses', array( $this, 'posts_clauses'));


    	if($query->have_posts())
    		return $query->post;

    	return false;

    }

    /**
     * Alter wordpress query to fetch only importers which have enabled and valid crons
     * @param  array $query 
     * @return array
     */
    public function posts_clauses($query){

    	global $wpdb;

    	$query['join'] .= " INNER JOIN {$wpdb->prefix}postmeta AS jci_pm1 ON ( {$wpdb->prefix}posts.ID = jci_pm1.post_id )
		INNER JOIN {$wpdb->prefix}postmeta AS jci_pm2 ON ( {$wpdb->prefix}posts.ID = jci_pm2.post_id )";
    	$query['where'] .= " AND
		(
			(
				jci_pm1.meta_key = '_jci_cron_last_ran'
				AND jci_pm2.meta_key = '_jci_cron_minutes'
				AND CAST(DATE_ADD(FROM_UNIXTIME(jci_pm1.meta_value), INTERVAL jci_pm2.meta_value MINUTE) AS DATETIME) <= NOW()
			)
			OR
			(
				jci_pm1.meta_key = '_jci_cron_last_ran'
				AND CAST(jci_pm1.meta_value AS CHAR) = '0'
			)
			
		)";
    	return $query;
    }

    /**
     * Add new 5 minute cron schedule
     * @param array $schedules
     */
    public function add_cron_interval( $schedules ){

    	$schedules['jci_1_minute'] = array(
	        'interval' => 60,
	        'display'  => esc_html__( 'Every Minute' ),
	    );
	 
	    return $schedules;
    }

}

new JCI_Cron();