<?php

/**
 *	phpIPAM agent class
 */

class phpipamAgent extends Common_functions {


	/**
	 * all possible connections
	 *
	 * (default value: null)
	 *
	 * @var null|array
	 * @access private
	 */
	private $types = null;

	/**
	 * config settings
	 *
	 * (default value: null)
	 *
	 * @var null|object
	 * @access protected
	 */
	protected $config = null;

	/**
	 * set mail override flag
	 *
	 * (default value: true)
	 *
	 * @var bool
	 * @access private
	 */
	private	$send_mail = true;

	/**
	 * array of address changes
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 */
	public $address_change = array();

	// set date for use throughout script

	/**
	 * time format
	 *
	 * (default value: false)
	 *
	 * @var bool
	 * @access private
	 */
	private $now     = false;

    /**
     * date format
     *
     * (default value: false)
     *
     * @var bool
     * @access private
     */
    private $nowdate = false;

	/**
	 *  agent details
	 *
	 * (default value: null)
	 *
	 * @var null|object
	 * @access private
	 */
	private $agent_details = null;

	/**
	 * connection type - selected
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access public
	 */
	public 	$conn_type = null;

	/**
	 * all connection types
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access private
	 */
	private $conn_types = null;

	/**
	 * scan type - selected
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access public
	 */
	public 	$scan_type = null;

	/**
	 * all scan types
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access private
	 */
	private $scan_types = null;

	/**
	 * ping type - selected
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access public
	 */
	public 	$ping_type = null;

	/**
	 * all ping types
	 *
	 * (default value: null)
	 *
	 * @var mixed
	 * @access public
	 */
	public 	$ping_types = null;

	/**
	 * for Database connection
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Database;

	/**
	 * scan object
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $Scan;



	/**
	 * __construct method
	 *
	 * @param resource $Database
	 * @return void
	 */
	public function __construct ($Database) {
		parent::__construct();
		// Result
		$this->Result = new Result ();
		// read config file
		$this->config = (object) Config::ValueOf('config');
		// set time
		$this->set_now_time ();
		// set valid connection types
		$this->set_valid_conn_types ();
		// set valid scan types
		$this->set_valid_scan_types ();
		// set valid scan types
		$this->set_valid_ping_types ();
		// set conn type
		$this->set_conn_type ();
		// set ping type
		$this->set_ping_type ();
		// validate php build
		$this->validate_php_build ();
		// validate threading
		$this->validate_threading ();
		// validate ping path
		$this->validate_ping_path ();
		// save database
		$this->Database = $Database;
	}

	/**
	 * Sets execution start date in time and date format
	 *
	 * @access private
	 * @return void
	 */
	private function set_now_time () {
    	$this->nowdate  = date("Y-m-d H:i:s");
    	$this->now      = strtotime($this->nowdate);
	}

	/**
	 * Defines valid connection types to phpipam server
	 *
	 * @access private
	 * @return void
	 */
	private function set_valid_conn_types () {
		// set valid types
		$this->conn_types = array(
							'api',
							'mysql'
						);
	}

	/**
	 * Sets valid scan types (update, discover)
	 *
	 * @access private
	 * @return void
	 */
	private function set_valid_scan_types () {
		// set valid types
		$this->scan_types = array(
							'update',
							'discover'
							);
	}

	/**
	 * Sets valid ping types (ping, fping, pear)
	 *
	 * @access private
	 * @return void
	 */
	private function set_valid_ping_types () {
		// set valid types
		$this->ping_types = array(
							'ping',
							'fping',
							'pear',
							'mikrotik'
							);
	}

	/**
	 * Validates connection type
	 *
	 * @access private
	 * @return void
	 */
	private function validate_conn_type () {
		//validate
		if (!in_array($this->config->type, $this->conn_types)) {
			$this->Result->throw_exception (500, "Invalid connection type!");
		}
	}

	/**
	 * Sets connection type to database
	 *
	 * @access private
	 * @return void
	 */
	private function set_conn_type () {
		// validate
		$this->validate_conn_type ();
		// save
		$this->conn_type = $this->config->type;
	}

	/**
	 * Sets type of scan to be executed
	 *
	 * @access public
	 * @param mixed $type
	 * @return void
	 */
	public function set_scan_type ($type) {
		//validate
		if (!in_array($type,$this->scan_types)) {
			$this->Result->throw_exception (500, "Invalid scan type - $type! Valid options are ".implode(", ", $this->scan_types));
		}
		// ok, save
		$this->scan_type = $type;
	}

	/**
	 * Sets type of ping to be executed
	 *
	 * @access public
	 * @return void
	 */
	public function set_ping_type () {
		//validate
		if (!in_array($this->config->method, $this->ping_types)) {
			$this->Result->throw_exception (500, "Invalid ping method - \$config['method'] = \"".escape_input($this->config->method)."\"");
		}
		// ok, save
		$this->ping_type = $this->config->method;
	}

	/**
	 * Validates php build
	 *
	 * @access private
	 * @return void
	 */
	private function validate_php_build () {
		// set required extensions
		$required_ext = $this->set_required_extensions ();
		// get available ext
		$available_ext = get_loaded_extensions();
		// loop and check
		foreach ($required_ext as $e) {
			if (!in_array($e, $available_ext)) {
				$missing_ext[] = $e;
			}
		}

		// die if missing
		if (isset($missing_ext)) {
		    $error[] = "The following required PHP extensions are missing for phpipam-agent:";
		    foreach ($missing_ext as $missing) {
		        $error[] ="  - ". $missing;
		    }
		    $error[] = "Please recompile PHP to include missing extensions.";

			// die
		    $this->Result->throw_exception (500, $error);
		}
	}

	/**
	 * Validates threading support
	 *
	 * @access private
	 * @return void
	 */
	private function validate_threading () {
		// mikrotik discovery does not fork worker threads
		if($this->config->method == "mikrotik") {
			return;
		}
		// only for threaded
		if($this->config->nonthreaded !== true) {
			// test to see if threading is available
			if(!PingThread::available($errmsg)) {
				$this->Result->throw_exception (500, "Threading is required for scanning subnets - Error: $errmsg\n");
			}
		}
	}

	/**
	 * Validate path for ping file
	 *
	 * @access private
	 * @return void
	 */
	private function validate_ping_path () {
		// mikrotik only needs a ping binary when online/offline checking is enabled
		if($this->config->method == "mikrotik") {
			$mikrotik = isset($this->config->mikrotik) ? (array) $this->config->mikrotik : array();
			if(empty($mikrotik['ping_check'])) {
				return;
			}
		}
		if(!file_exists($this->config->pingpath)) {
			$this->Result->throw_exception (500, "ping executable does not exist - \$config['pingpath'] = \"".escape_input($this->config->pingpath)."\"");
		}
	}

	/**
	 * Sets required extensions for agent to run
	 *
	 * @access private
	 * @return void
	 */
	private function set_required_extensions () {
		// general
		$required_ext = array("gmp", "json", "pcntl");

		// if mysql selected
		if ($this->config->type=="mysql") {
			$required_ext = array_merge($required_ext, array("PDO", "pdo_mysql"));
		}
		// if non-threaded permitted remove pcntl requirement
		if ($this->config->nonthreaded === true) {
			unset($required_ext[2]);
		}
		// mikrotik discovery does not fork worker threads (no pcntl required)
		if ($this->config->method == "mikrotik") {
			$required_ext = array_diff($required_ext, array("pcntl"));
		}
		// if api selected

		// result
		return $required_ext;
	}

 	/**
 	 * Resolves hostname
 	 *
 	 * @access public
 	 * @param object $address
 	 * @param boolean $override override DNS resolving flag
 	 * @return array
 	 */
 	public function resolve_address ($address, $override=false) {
	 	# make sure it is dotted format
	 	$address->ip = $this->transform_address ($address->ip_addr, "dotted");
		# if hostname is set try to check
		if(empty($address->hostname) || is_null($address->hostname)) {
			# if permitted in settings
			if($this->settings->enableDNSresolving == 1 || $override) {
				# resolve
				$resolved = gethostbyaddr($address->ip);
				if($resolved==$address->ip)		$resolved="";			//resolve fails

				return array("class"=>"resolved", "name"=>$resolved);
			}
			else {
				return array("class"=>"", "name"=>"");
			}
		}
		else {
				return array("class"=>"", "name"=>$address->hostname);
		}
	}

	/**
	 * Prints success
	 *
	 * @access private
	 * @param string|array $text
	 * @return void
	 */
	private function print_success ($text) {
		// array ?
		if (is_array($text)) {
			foreach ($text as $t) {
				$success[] = $t;
			}
			$success[] = "";
		} else {
			$success[] = $text."\n";
		}
		// print
		print implode("\n",$success);
	}

	/**
	 * Executes scan / discover.
	 *
	 * @access public
	 * @return void
	 */
	public function execute () {
		// initialize proper function
		$init = 'initialize_'.$this->conn_type;
		// init
		return $this->{$init} ();
	}


	/**
	 * Sets scan object
	 *
	 * @access private
	 * @return void
	 */
	private function scan_set_object () {
		# initialize objects
		$this->Scan	= new Scan ($this->Database);
		// set ping statuses
		$statuses = explode(";", $this->settings->pingStatus);
	}









	/**
	 * @api functions
	 * ---------------------------------
	 */

	/**
	 * Initialize API
	 *
	 * @access private
	 * @return void
	 */
	private function initialize_api () {
		$this->Result->throw_exception (500, "API agent type not yet supported!");
	}









	/**
	 * @mysql functions
	 * ---------------------------------
	 */

	/**
	 * Initialized mysql connection
	 *
	 * @access private
	 * @return void
	 */
	private function initialize_mysql () {
		// test connection
		$this->mysql_test_connection ();

		// validate key and fetch agent
		$this->mysql_validate_key ();

		// fetch settings
		$this->mysql_fetch_settings ();

		// initialize scan object
		$this->scan_set_object ();

		// mikrotik DHCP lease discovery - populate from RouterOS instead of ICMP scanning
		if ($this->config->method == "mikrotik") {
			return $this->mysql_scan_mikrotik ($this->scan_type == "discover");
		}

		// we have subnets, now check
		return $this->scan_type == "update" ? $this->mysql_scan_update_host_statuses () : $this->mysql_scan_discover_hosts ();
	}

	/**
	 * Test connection to database server.
	 *
	 *	Will throw exception if failure
	 *
	 * @access private
	 * @return void
	 */
	private function mysql_test_connection () {
		$this->Database->connect();
	}

	/**
	 * Validates key for phpipam agent and fetches id
	 *
	 * @access private
	 * @return void
	 */
	private function mysql_validate_key () {
		// fetch details
		try { $agent = $this->Database->getObjectQuery("select * from `scanAgents` where `code` = ? and `type` = 'mysql' limit 1;", array($this->config->key)); }
		catch (Exception $e) {
			$this->Result->throw_exception (500, "Error: ".$e->getMessage());
		}
		// invalid
		if (is_null($agent)) {
			$this->Result->throw_exception (500, "Error: Invalid agent code");
		}
		// save agent details
		else {
			$this->agent_details = $agent;
		}
	}

	/**
	 * Update last check for agent
	 *
	 * @access public
	 * @return void
	 */
	public function update_agent_scantime () {
		// update access time
		try { $agent = $this->Database->runQuery("update `scanAgents` set `last_access` = ? where `id` = ? limit 1;", array($this->nowdate, $this->agent_details->id)); }
		catch (Exception $e) {
			$this->Result->throw_exception (500, "Error: ".$e->getMessage());
		}
	}

	/**
	 * Fetches settings from master database
	 *
	 * @access private
	 * @return void
	 */
	private function mysql_fetch_settings () {
		# fetch
		try { $settings = $this->Database->getObject("settings", 1); }
		catch (Exception $e) {
			$this->Result->throw_exception (500, "Error: ".$e->getMessage());
		}
		# save
		$this->settings = $settings;
	}


	/**
	 * Check for last statuses of hosts
	 *
	 * @access public
	 * @return void
	 */
	public function mysql_scan_update_host_statuses () {
		// prepare addresses
		$subnets = $this->mysql_fetch_subnets ($this->agent_details->id);
		// fetch addresses
		$addresses = $this->mysql_fetch_addresses ($subnets, "update");
		// save existing and reindexed
		$addresses_tmp = $addresses[0];
		$addresses 	   = (array) $addresses[1];

		// non-threaded?
		if ($this->config->nonthreaded === true) {
			// execute
			if ($this->ping_type=="fping")	{ $subnets = $this->mysql_scan_discover_hosts_fping_nonthreaded ($subnets, $addresses_tmp); }
			else							{ $subnets = $this->mysql_scan_discover_hosts_ping_nonthreaded  ($subnets, $addresses); }
		}
		else {
			// execute
			if ($this->ping_type=="fping")	{ $subnets = $this->mysql_scan_discover_hosts_fping ($subnets, $addresses_tmp); }
			else							{ $subnets = $this->mysql_scan_discover_hosts_ping  ($subnets, $addresses); }
		}

		// update database and send mail if requested
		$this->mysql_scan_update_write_to_db ($subnets);
		// reset dhcp
		if($this->config->remove_inactive_dhcp===true) {
			$this->reset_dhcp_addresses();
		}
		// reset autodiscovered DHCP addresses
		if($this->config->reset_autodiscover_addresses===true) {
			$this->reset_autodiscover_addresses();
		}

		// updatelast scantime
		foreach ($subnets as $s) {
			$this->update_subnet_status_scantime ($s->id);
		}
	}

	/**
	 * Update last scan date
	 *
	 * @method update_subnet_status_scantime
	 * @param  int $subnet_id
	 * @return void
	 */
	private function update_subnet_status_scantime ($subnet_id) {
		try { $this->Database->updateObject("subnets", array("id"=>$subnet_id, "lastScan"=>$this->nowdate), "id"); }
		catch (Exception $e) {}
	}

	/**
	 * Scan for new hosts in selected networks
	 *
	 * @access public
	 * @return void
	 */
	public function mysql_scan_discover_hosts () {
		// prepare addresses
		$subnets = $this->mysql_fetch_subnets ($this->agent_details->id);
		// fetch addresses
		$addresses = $this->mysql_fetch_addresses ($subnets, "discovery");
		// save existing and reindexed
		$addresses_tmp = $addresses[0];
		$addresses 	   = $addresses[1];

		// execute
		if ($this->ping_type=="fping")	{ $subnets = $this->mysql_scan_discover_hosts_fping ($subnets, $addresses_tmp); }
		else							{ $subnets = $this->mysql_scan_discover_hosts_ping  ($subnets, $addresses); }

		// update database and send mail if requested
		$this->mysql_scan_discovered_write_to_db ($subnets);
		// updatelast scantime
		foreach ($subnets as $s) {
			$this->update_subnet_discovery_scantime ($s->id);
		}
	}

	/**
	 * Update last discovery date
	 *
	 * @method update_subnet_discovery_scantime
	 * @param  int $subnet_id
	 * @return void
	 */
	private function update_subnet_discovery_scantime ($subnet_id) {
		try { $this->Database->updateObject("subnets", array("id"=>$subnet_id, "lastDiscovery"=>$this->nowdate), "id"); }
		catch (Exception $e) {}
	}

	/**
	 * This function fetches id, subnet and mask for all subnets
	 *
	 * @access private
	 * @param int $agentId (default:null)
	 * @return void
	 */
	private function mysql_fetch_subnets ($agentId = null ) {
		# null
		if (is_null($agentId) || !is_numeric($agentId))	{ return false; }
		# get type
		$type = $this->get_scan_type_field ();
		# fetch
		try { $subnets = $this->Database->getObjectsQuery("SELECT `id`,`subnet`,`sectionId`,`mask`,`resolveDNS`,`nameserverId` FROM `subnets` WHERE `scanAgent` = ? AND `$type` = 1 AND `isFolder` = 0 AND `mask` > 0;", array($agentId)); }
		catch (Exception $e) {
			$this->Result->throw_exception (500, "Error: ".$e->getMessage());
		}
		# die if nothing to scan
		if (sizeof($subnets)==0)	{ die(); }
        // if subnet has slaves dont check it
        foreach ($subnets as $k=>$s) {
    		try { $count = $this->Database->numObjectsFilter("subnets", "masterSubnetId", $s->id); }
    		catch (Exception $e) {
    			$this->Result->show("danger", _("Error: ").$e->getMessage());
    			return false;
    		}
        	if ($count>0) {
        		unset($subnets[$k]);
        	}
    	}
		# die if nothing to scan
		if (!isset($subnets))	   { die(); }
		# result
		return $subnets;
	}

	/**
	 * Fetches addresses to scan
	 *
	 * @access private
	 * @param array $subnets
	 * @param string $type (discovery, update)
	 * @return void
	 */
	private function mysql_fetch_addresses ($subnets, $type) {
		// array check
		if(!is_array($subnets))     { die(); }
		// loop through subnets and save addresses to scan
		foreach($subnets as $s) {
			// if subnet has slaves dont check it
			if ($this->mysql_check_slaves ($s->id) === false) {
				$addresses_tmp[$s->id] = $this->Scan->prepare_addresses_to_scan ($type, $s->id);
			}
		}
		// if false exit
		if(!isset($addresses_tmp))	{ die(); }

		// reindex
		if (isset($addresses_tmp)) {
			foreach($addresses_tmp as $s_id=>$a) {
				foreach($a as $ip) {
					$addresses[] = array("subnetId"=>$s_id, "ip_addr"=>$ip);
				}
			}
		}
		else {
			$addresses_tmp 	= array();
			$addresses 		= array();
		}
		// return result - $addresses_tmp = existing, $addresses = reindexed
		return array($addresses_tmp, $addresses);
	}

	/**
	 * Check if subnet has slaves
	 *
	 * @access private
	 * @param int $subnetId
	 * @return void
	 */
	private function mysql_check_slaves ($subnetId) {
		// int check
		if(!is_numeric($subnetId))	{ return false; }
		// check
		try { $count = $this->Database->numObjectsFilter("subnets", "masterSubnetId", $subnetId); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage());
			return false;
		}
		# result
		return $count>0 ? true : false;
	}

	/**
	 * Discover new host with fping
	 *
	 * @access private
	 * @param array $subnets
	 * @param array $addresses_tmp
	 * @return void
	 */
	private function mysql_scan_discover_hosts_fping ($subnets, $addresses_tmp) {
		$z = 0;			//addresses array index

		//run per MAX_THREADS
		for ($m=0; $m<sizeof($subnets); $m += $this->config->threads) {
		    // create threads
		    $threads = array();
		    //fork processes
		    for ($i = 0; $i < $this->config->threads; $i++) {
		    	//only if index exists!
		    	if(isset($subnets[$z])) {
					//start new thread
		            $threads[$z] = new PingThread( 'fping_subnet' );
					$threads[$z]->start_fping( $this->transform_to_dotted($subnets[$z]->subnet)."/".$subnets[$z]->mask );
				}
	            $z++;				//next index
		    }
		    // wait for all the threads to finish
		    while( !empty( $threads ) ) {
				foreach($threads as $index => $thread) {
					$child_pipe = "/tmp/pipe_".$thread->getPid();

					if (file_exists($child_pipe)) {
						$file_descriptor = fopen( $child_pipe, "r");
						$child_response = "";
						while (!feof($file_descriptor)) {
							$child_response .= fread($file_descriptor, 8192);
						}
						//we have the child data in the parent, but serialized:
						$child_response = unserialize( $child_response );
						//store
						$subnets[$index]->discovered = $child_response;
						//now, child is dead, and parent close the pipe
						unlink( $child_pipe );
						unset($threads[$index]);
					}
				}
		    }
		}

		//fping finds all subnet addresses, we must remove existing ones !
		foreach($subnets as $sk=>$s) {
			if (is_array($s->discovered)) {
				foreach($s->discovered as $rk=>$result) {
					if(!in_array($this->transform_to_decimal($result), $addresses_tmp[$s->id])) {
						unset($subnets[$sk]->discovered[$rk]);
					}
				}
				//rekey
				$subnets[$sk]->discovered = array_values($subnets[$sk]->discovered);
			}
		}

		// return result
		return $subnets;
	}

	/**
	 * Discover new hosts with ping or pear
	 *
	 * @access private
	 * @param mixed $subnets
	 * @param mixed $addresses
	 * @return void
	 */
	private function mysql_scan_discover_hosts_ping ($subnets, $addresses) {
		$z = 0;			//addresses array index

		//run per MAX_THREADS
        $num_of_addresses = sizeof($addresses);
        for ($m=0; $m<$num_of_addresses; $m += $this->config->threads) {

	        // create threads
	        $threads = array();

	        //fork processes
	        for ($i = 0; $i < $this->config->threads; $i++) {
	        	//only if index exists!
	        	if(isset($addresses[$z])) {
					//start new thread
		            $threads[$z] = new PingThread( 'ping_address' );
		            $threads[$z]->start( $this->transform_to_dotted( $addresses[$z]['ip_addr']) );
				}
				$z++;			//next index
	        }

	        // wait for all the threads to finish
	        while( !empty( $threads ) ) {
	            foreach( $threads as $index => $thread ) {
	                if( !$thread->isAlive() ) {
						//unset dead hosts
						if($thread->getExitCode() != 0) {
							unset($addresses[$index]);
						}
	                    //remove thread
	                    unset($threads[$index]);
	                }
	            }
	        }
		}

		//ok, we have all available addresses, rekey them
		if (sizeof($addresses)>0) {
			foreach($addresses as $a) {
				$add_tmp[$a['subnetId']][] = $this->transform_to_dotted($a['ip_addr']);
			}
			//add to scan_subnets as result
			foreach($subnets as $sk=>$s) {
				if(isset($add_tmp[$s->id])) {
					$subnets[$sk]->discovered = $add_tmp[$s->id];
				}
			}
		}

		// return result
		return $subnets;
	}


	/**
	 * Discover new host with fping - nonthreaded
	 *
	 * @access private
	 * @param mixed $subnets
	 * @return void
	 */
	private function mysql_scan_discover_hosts_fping_nonthreaded ($subnets, $addresses_tmp) {
		foreach($subnets as $sk=>$s) {
			// ping
			$subnets[$sk]->discovered = fping_subnet ($this->transform_to_dotted($s->subnet)."/".$s->mask);
		}
/*

		// run separately for each host
		foreach ($address as $a) {
			// ping
			$ping = fping_subnet ($this->transform_to_dotted($subnets[$z]->subnet)."/".$subnets[$z]->mask );
			// check result
			var_dump($ping);
		}

		//run per MAX_THREADS
		for ($m=0; $m<=sizeof($subnets); $m += $this->config->threads) {
		    // create threads
		    $threads = array();
		    //fork processes
		    for ($i = 0; $i <= $this->config->threads && $i <= sizeof($subnets); $i++) {
		    	//only if index exists!
		    	if(isset($subnets[$z])) {
					//start new thread
		            $threads[$z] = new PingThread( 'fping_subnet' );
					$threads[$z]->start_fping( $this->transform_to_dotted($subnets[$z]->subnet)."/".$subnets[$z]->mask );
		            $z++;				//next index
				}
		    }
		    // wait for all the threads to finish
		    while( !empty( $threads ) ) {
				foreach($threads as $index => $thread) {
					$child_pipe = "/tmp/pipe_".$thread->getPid();

					if (file_exists($child_pipe)) {
						$file_descriptor = fopen( $child_pipe, "r");
						$child_response = "";
						while (!feof($file_descriptor)) {
							$child_response .= fread($file_descriptor, 8192);
						}
						//we have the child data in the parent, but serialized:
						$child_response = unserialize( $child_response );
						//store
						$subnets[$index]->discovered = $child_response;
						//now, child is dead, and parent close the pipe
						unlink( $child_pipe );
						unset($threads[$index]);
					}
				}
		        usleep(200000);
		    }
		}

		//fping finds all subnet addresses, we must remove existing ones !
		foreach($subnets as $sk=>$s) {
			if (is_array($s->discovered)) {
				foreach($s->discovered as $rk=>$result) {
					if(!in_array($this->transform_to_decimal($result), $addresses_tmp[$s->id])) {
						unset($subnets[$sk]->discovered[$rk]);
					}
				}
				//rekey
				$subnets[$sk]->discovered = array_values($subnets[$sk]->discovered);
			}
		}
*/

		// return result
		return $subnets;
	}

	/**
	 * Discover new hosts with ping or pear - nonthreaded!
	 *
	 * @access private
	 * @param mixed $subnets
	 * @param mixed $addresses
	 * @return $subnets
	 */
	private function mysql_scan_discover_hosts_ping_nonthreaded ($subnets, $addresses) {

		for ($i = 0; $i <= sizeof($addresses); $i++) {
			ping_address( $this->transform_to_dotted( $addresses[$i]['ip_addr']) );
		}

		//ok, we have all available addresses, rekey them
		if (sizeof($addresses)>0) {
			foreach($addresses as $a) {
				$add_tmp[$a['subnetId']][] = $this->transform_to_dotted($a['ip_addr']);
			}
			//add to scan_subnets as result
			foreach($subnets as $sk=>$s) {
				if(isset($add_tmp[$s->id])) {
					$subnets[$sk]->discovered = $add_tmp[$s->id];
				}
			}
		}

		// return result
		return $subnets;
	}

	/**
	 * Write discovered hosts to database
	 *
	 * @access private
	 * @param mixed $subnets
	 * @return void
	 */
	private function mysql_scan_discovered_write_to_db ($subnets) {
		# insert to database
		$discovered = 0;				//for mailing

		# reset db connection for ping / pear
		if ($this->scan_type!=="fping") {
			unset($this->Database);
			$this->Database = new Database_PDO ();
		}
		// loop
		foreach($subnets as $s) {
			if (isset($s->discovered) && is_array($s->discovered)) {
				foreach($s->discovered as $ip) {
					// try to resolve hostname
					$tmp = new stdClass();
					$tmp->ip_addr = $ip;
					$hostname = $this->resolve_address($tmp, true);
					//set update query
					$values = array("subnetId"=>$s->id,
									"ip_addr"=>$this->transform_address($ip, "decimal"),
									"hostname"=>$hostname['name'],
									"description"=>"-- autodiscovered --",
									"note"=>"This host was autodiscovered on ".$this->nowdate. " by agent ".$this->agent_details->name,
									"lastSeen"=>$this->nowdate,
									"state"=>"2"
									);
					//insert
					$this->mysql_insert_address($values);

					//set discovered
					$discovered++;
				}
			}
		}

		// mail ?
		if($discovered>0 && $this->config->sendmail===true) {
			$this->scan_discovery_send_mail ();
		}

		// ok
		return true;
	}

	/**
	 * Inserts new address to database
	 *
	 * @access private
	 * @param mixed $insert
	 * @return void
	 */
	private function mysql_insert_address ($insert) {
		# execute
		try { $this->Database->insertObject("ipaddresses", $insert); }
		catch (Exception $e) {
			$this->Result->throw_exception (500, "Error: ".$e->getMessage());
			return false;
		}
		# ok
		return true;
	}

	/**
	 * Update statuses for alive hosts
	 *
	 * @access private
	 * @param mixed $subnets
	 * @return voi
	 */
	private function mysql_scan_update_write_to_db ($subnets) {
		# reset db connection for ping / pear
		if ($this->scan_type!=="fping") {
			unset($this->Database);
			$this->Database = new Database_PDO ();
		}
		// loop
		foreach ($subnets as $s) {
			if (is_array($s->discovered)) {
				foreach ($s->discovered as $ip) {
					# execute
					$query = "update `ipaddresses` set `lastSeen` = ? where `subnetId` = ? and `ip_addr` = ? limit 1;";
					$vars  = array($this->nowdate, $s->id, $this->transform_address($ip, "decimal"));

					try { $this->Database->runQuery($query, $vars); }
					catch (Exception $e) {
						$this->Result->throw_exception(500, "Error: ".$e->getMessage());
					}
				}
			}
		}

	}

	/**
	 * Resets DHCP Adresses
	 *
	 * @access private
	 * @return void
	 */
	private function reset_dhcp_addresses () {

		# reset db connection
		unset($this->Database);
		$this->Database = new Database_PDO ();

		# Get all used DHCP addresses
		$query = "SELECT `ip_addr`, `subnetId`, `lastSeen` FROM `ipaddresses` WHERE `state` = ? AND NOT `lastSeen` = ?;";
		$vars = array("4", "0000-00-00 00:00:00");

		// fetch details
                try { $DHCPAddresses = $this->Database->getObjectsQuery($query, $vars); }
		catch (Exception $e) {
		$this->Result->throw_exception (500, "Error: ".$e->getMessage());
		}

		# Get Warning and Offline time
		$query = "select `pingStatus` from `settings`;";

		// fetch details
                try { $statuses = $this->Database->getObjectsQuery($query); }
                catch (Exception $e) {
                $this->Result->throw_exception (500, "Error: ".$e->getMessage());
                }

		# Convert stdClass Objects to arrays
		$statuses = json_decode(json_encode($statuses), True);
		$DHCPAddresses = json_decode(json_encode($DHCPAddresses), True);

		$statuses = explode(";", $statuses['0']['pingStatus']);

		foreach ($DHCPAddresses as $addr) {
			$tDiff = time() - strtotime($addr['lastSeen']);

			if ( $tDiff > $statuses['1'])
			{
				$query = "UPDATE `ipaddresses` SET `lastSeen` = ?, hostname = '' WHERE `subnetId` = ? AND `ip_addr` = ? limit 1;";
				$vars  = array("0000-00-00 00:00:00", $addr['subnetId'], $addr['ip_addr']);

				try { $this->Database->runQuery($query, $vars); }
				catch (Exception $e) {
					$this->Result->throw_exception(500, "Error: ".$e->getMessage());
				}
			}
		}
	}


	/**
	 * Resets autodiscovered Adresses
	 *
	 * @access private
	 * @return void
	 */
	private function reset_autodiscover_addresses () {

		# reset db connection
		unset($this->Database);
		$this->Database = new Database_PDO ();

		# Get all autodiscoverd IPs
		$query = "SELECT `ip_addr`, `subnetId`, `lastSeen` FROM `ipaddresses` WHERE `description` = ? AND NOT `lastSeen` = ?;";
		$vars = array("-- autodiscovered --", "0000-00-00 00:00:00");

		// fetch details
        try { $AutoDiscAddresses = $this->Database->getObjectsQuery($query, $vars); }
		catch (Exception $e) {
			$this->Result->throw_exception (500, "Error: ".$e->getMessage());
		}

		# Get Warning and Offline time
		$query = "select `pingStatus` from `settings`;";

		// fetch details
        try { $statuses = $this->Database->getObjectsQuery($query); }
        catch (Exception $e) {
        	$this->Result->throw_exception (500, "Error: ".$e->getMessage());
        }

		# Convert stdClass Objects to arrays
		$statuses = json_decode(json_encode($statuses), True);
		$AutoDiscAddresses = json_decode(json_encode($AutoDiscAddresses), True);

		$statuses = explode(";", $statuses['0']['pingStatus']);

		foreach ($AutoDiscAddresses as $addr) {
			$tDiff = time() - strtotime($addr['lastSeen']);

			if ( $tDiff > $statuses['1']) {
				## Delete IP
				$field  = "subnetId";   $value  = $addr['subnetId'];
	            $field2 = "ip_addr";    $value2 = $addr['ip_addr'];

		        try { $this->Database->deleteRow("ipaddresses", $field, $value, $field2, $value2); }
				catch (Exception $e) {
                		$this->Result->throw_exception (500, "Error: ".$e->getMessage());
		        }
			}
		}
	}


	/**
	 * @mikrotik functions
	 * ---------------------------------
	 */

	/**
	 * Pulls DHCP leases from the configured MikroTik routers and writes them
	 * to phpipam.
	 *
	 *	Each lease is matched into one of the subnets assigned to this agent,
	 *	tagged as dynamic or static and (optionally) fping-checked for its
	 *	online/offline status.
	 *
	 *	In "discover" mode new leases are inserted and existing addresses
	 *	updated; in "update" mode only addresses already present in the
	 *	database are refreshed.
	 *
	 * @access private
	 * @param bool $insert_new whether brand new leases should be inserted
	 * @return bool
	 */
	private function mysql_scan_mikrotik ($insert_new) {
		// config
		$mikrotik = isset($this->config->mikrotik) ? (array) $this->config->mikrotik : array();
		if (empty($mikrotik['routers']) || !is_array($mikrotik['routers'])) {
			$this->Result->throw_exception (500, "No MikroTik routers configured - \$config['mikrotik']['routers'] is empty");
		}
		$ping_check  = !empty($mikrotik['ping_check']);
		$desc_prefix = isset($mikrotik['description']) ? $mikrotik['description'] : "MikroTik DHCP lease";
		$tag_dynamic = isset($mikrotik['tag_dynamic']) ? $mikrotik['tag_dynamic'] : 4;
		$tag_static  = isset($mikrotik['tag_static'])  ? $mikrotik['tag_static']  : 2;

		// fetch subnets assigned to this agent and pre-compute their boundaries
		$subnets = $this->mysql_fetch_subnets ($this->agent_details->id);
		$ranges  = $this->mikrotik_subnet_ranges ($subnets);

		// collect leases from all routers (socket work, before touching the db)
		$leases = $this->mikrotik_fetch_leases ($mikrotik['routers']);

		// reset db connection - it may have idled while talking to the routers
		unset($this->Database);
		$this->Database = new Database_PDO ();
		$Addresses = new Addresses ($this->Database);

		$inserted = 0; $updated = 0; $touched_subnets = array();

		foreach ($leases as $lease) {
			// RouterOS uses 'address' for the leased IP
			if (empty($lease['address']) || filter_var($lease['address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
				continue;
			}
			$ip_dotted  = $lease['address'];
			$ip_decimal = $this->transform_to_decimal ($ip_dotted);

			// find the agent subnet this lease belongs to
			$subnetId = $this->mikrotik_match_subnet ($ip_decimal, $ranges);
			if ($subnetId === false) {
				continue;
			}

			// dynamic vs static lease
			$is_dynamic = isset($lease['dynamic']) && $lease['dynamic'] === "true";
			$lease_type = $is_dynamic ? "dynamic" : "static";
			$state      = $is_dynamic ? $tag_dynamic : $tag_static;

			// hostname / mac
			$hostname = isset($lease['host-name']) ? $lease['host-name'] : "";
			$mac      = isset($lease['mac-address']) ? strtolower($lease['mac-address']) : "";

			// online status
			if ($ping_check) {
				$online = $this->Scan->ping_address ($ip_dotted) == 0;
			} else {
				// trust the RouterOS lease status when not pinging
				$online = isset($lease['status']) ? ($lease['status'] === "bound") : true;
			}

			// description - for static leases prefer the RouterOS comment when set
			$comment = isset($lease['comment']) ? trim($lease['comment']) : "";
			if (!$is_dynamic && strlen($comment) > 0) {
				$description = $comment;
			} else {
				$description = $desc_prefix." (".$lease_type.")";
			}
			$note        = "MikroTik DHCP ".$lease_type." lease imported on ".$this->nowdate." by agent ".$this->agent_details->name;

			// already known ?
			$existing = $Addresses->fetch_address_multiple_criteria ($ip_decimal, $subnetId);

			if (is_object($existing)) {
				$values = array(
					"id"          => $existing->id,
					"mac"         => $mac,
					"description" => $description,
					"note"        => $note,
					"state"       => $state,
				);
				if (strlen($hostname) > 0)	{ $values['hostname'] = $hostname; }
				if ($online)				{ $values['lastSeen']  = $this->nowdate; }

				try { $this->Database->updateObject("ipaddresses", $values, "id"); $updated++; }
				catch (Exception $e) { $this->Result->throw_exception (500, "Error: ".$e->getMessage()); }
			}
			elseif ($insert_new) {
				$values = array(
					"subnetId"    => $subnetId,
					"ip_addr"     => $ip_decimal,
					"mac"         => $mac,
					"hostname"    => $hostname,
					"description" => $description,
					"note"        => $note,
					"state"       => $state,
					"lastSeen"    => $online ? $this->nowdate : "0000-00-00 00:00:00",
				);
				$this->mysql_insert_address ($values);
				$inserted++;
			}
			else {
				continue;
			}

			$touched_subnets[$subnetId] = true;
		}

		// update subnet scan timestamps
		foreach (array_keys($touched_subnets) as $sid) {
			if ($insert_new)	{ $this->update_subnet_discovery_scantime ($sid); }
			else				{ $this->update_subnet_status_scantime ($sid); }
		}

		$this->print_success ("MikroTik DHCP import complete: ".$inserted." inserted, ".$updated." updated.");

		return true;
	}

	/**
	 * Pre-computes the decimal network/broadcast boundaries for each subnet so
	 * leases can be matched into the right subnet.
	 *
	 * @access private
	 * @param array $subnets
	 * @return array  list of array("id"=>, "min"=>, "max"=>)
	 */
	private function mikrotik_subnet_ranges ($subnets) {
		$ranges = array();
		if (!is_array($subnets)) {
			return $ranges;
		}
		$Subnets = new Subnets ($this->Database);
		foreach ($subnets as $s) {
			// only IPv4 subnets can hold MikroTik DHCP leases
			if ($this->identify_address ($this->transform_to_dotted($s->subnet)) != "IPv4") {
				continue;
			}
			$b = (object) $Subnets->get_network_boundaries ($s->subnet, $s->mask);
			$ranges[] = array(
				"id"  => $s->id,
				"min" => $this->transform_to_decimal ($b->network),
				"max" => $this->transform_to_decimal ($b->broadcast),
			);
		}
		return $ranges;
	}

	/**
	 * Returns the id of the subnet that contains the given decimal IP, or
	 * false when the IP is outside every assigned subnet.
	 *
	 * @access private
	 * @param string $ip_decimal
	 * @param array  $ranges
	 * @return int|false
	 */
	private function mikrotik_match_subnet ($ip_decimal, $ranges) {
		foreach ($ranges as $r) {
			if (gmp_cmp($ip_decimal, $r['min']) >= 0 && gmp_cmp($ip_decimal, $r['max']) <= 0) {
				return $r['id'];
			}
		}
		return false;
	}

	/**
	 * Connects to every configured router and returns the combined list of
	 * DHCP leases. A single unreachable router does not abort the run.
	 *
	 * @access private
	 * @param array $routers
	 * @return array
	 */
	private function mikrotik_fetch_leases ($routers) {
		$leases = array();
		foreach ($routers as $r) {
			if (empty($r['host'])) {
				continue;
			}
			$port = isset($r['port']) ? $r['port'] : 8728;
			$user = isset($r['user']) ? $r['user'] : "";
			$pass = isset($r['pass']) ? $r['pass'] : "";

			try {
				$api = new RouterOS_API ($r['host'], $user, $pass, $port);
				$api->connect ();
				$api->login ();
				$router_leases = $api->get_dhcp_leases ();
				$api->disconnect ();
			}
			catch (Exception $e) {
				// keep going - one bad router shouldn't kill the whole import
				$this->print_success ("Warning: MikroTik ".$r['host']." - ".$e->getMessage());
				continue;
			}

			foreach ($router_leases as $l) {
				$leases[] = $l;
			}
		}
		return $leases;
	}


	/**
	 * @common functions for both methods
	 * ---------------------------------
	 */

	/**
	 * Sets mysql field name from scan type
	 *
	 * @access private
	 * @return void
	 */
	private function get_scan_type_field () {
		if ($this->scan_type == "update")			{ return "pingSubnet"; }
		elseif ($this->scan_type == "discover")		{ return "discoverSubnet"; }
		else 										{ $this->Result->throw_exception (500, "Invalid scan type!"); }
	}


	private function scan_discovery_send_mail () {

	}

}
