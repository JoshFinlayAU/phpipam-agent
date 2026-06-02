<?php

/*	phpipam agent config file
 ******************************/

# set connection type
# 	api,mysql;
# ******************************/
$config['type'] = "mysql";

# set agent key
# ******************************/
$config['key'] = "aad984d8314fcf644d3fb46886ea461f";

# set scan method and path to ping file
#	ping, fping, pear or mikrotik
#
#	mikrotik : instead of ICMP scanning, pull active DHCP leases from one or
#	           more MikroTik routers via the RouterOS binary API (see the
#	           $config['mikrotik'] block below). When 'ping_check' is enabled
#	           fping is additionally used to set online/offline status, so
#	           $config['pingpath'] must point at the fping binary.
# ******************************/
//$config['method'] 	= "pear";
//$config['pingpath'] = "/sbin/ping";

$config['method'] 	= "fping";
$config['pingpath'] = "/usr/local/sbin/fping";

# MikroTik RouterOS DHCP lease discovery (used when $config['method'] = "mikrotik")
#
#	'routers'     : list of routers to query. Each lease is matched into the
#	                subnets assigned to this scan agent. Only the plaintext
#	                binary API (default port 8728) is supported.
#	'ping_check'  : if true, fping each leased address to set its online/offline
#	                status (requires $config['pingpath'] to be the fping binary).
#	'description' : text written to the address description; the lease type
#	                "(dynamic)" or "(static)" is appended automatically.
#	'tag_dynamic' : phpipam IP tag (ipTags.id) used for dynamic leases (4 = DHCP)
#	'tag_static'  : phpipam IP tag (ipTags.id) used for static  leases (2 = Used)
# ******************************/
$config['mikrotik']['routers'] = array(
	array(
		'host' => "192.168.88.1",
		'user' => "phpipam",
		'pass' => "password",
		'port' => 8728,
	),
	// add more routers here ...
);
$config['mikrotik']['ping_check']  = true;
$config['mikrotik']['description'] = "MikroTik DHCP lease";
$config['mikrotik']['tag_dynamic'] = 4;
$config['mikrotik']['tag_static']  = 2;

# permit non-threaded checks (default: false)
# ******************************/
$config['nonthreaded'] = false;

# how many concurrent threads (default: 32)
# ****************************************/
$config['threads']  = 32;

# api settings, if api selected
# ******************************/
$config['api']['key'] = "";

# send mail diff
# ******************************/
$config['sendmail'] = false;

# remove inactive DHCP addresses
#
# 	reset_autodiscover_addresses: will remove addresses if description -- autodiscovered -- and is offline
# 	remove_inactive_dhcp		: will remove inactive dhcp addresses
# ******************************/
$config['reset_autodiscover_addresses'] = false;
$config['remove_inactive_dhcp']         = false;


# mysql db settings, if mysql selected
# ******************************/
$config['db']['host'] = "localhost";
$config['db']['user'] = "phpipam";
$config['db']['pass'] = "phpipamadmin";
$config['db']['name'] = "phpipam";
$config['db']['port'] = 3306;

/**
 *  SSL options for MySQL
 *
 See http://php.net/manual/en/ref.pdo-mysql.php
     https://dev.mysql.com/doc/refman/5.7/en/ssl-options.html

     Please update these settings before setting 'ssl' to true.
     All settings can be commented out or set to NULL if not needed

     php 5.3.7 required
 ******************************/
$config['db']['ssl']        = false;                           // true/false, enable or disable SSL as a whole
$config['db']['ssl_key']    = '/path/to/cert.key';             // path to an SSL key file. Only makes sense combined with ssl_cert
$config['db']['ssl_cert']   = '/path/to/cert.crt';             // path to an SSL certificate file. Only makes sense combined with ssl_key
$config['db']['ssl_ca']     = '/path/to/ca.crt';               // path to a file containing SSL CA certs
$config['db']['ssl_capath'] = '/path/to/ca_certs';             // path to a directory containing CA certs
$config['db']['ssl_cipher'] = 'DHE-RSA-AES256-SHA:AES128-SHA'; // one or more SSL Ciphers
$config['db']['ssl_verify'] = true;                            // Verify Common Name (CN) of server certificate?
