## Description
phpipam-agent is a scanning agent for a phpipam server to be deployed to remote servers

## License
phpipam is released under the GPL v3 license.  See misc/gpl-3.0.txt.

## Requirements
 - 64bit PHP version 5.4+ with following modules
    - pdo, pdo_mysql : Adds support for mysql connections (if type=mysql)
    - gmp            : Adds support for dev-libs/gmp (GNU MP library) -> to calculate IPv6 networks
    - json           : Adds supports for JSON data-interexchange format
    - pcntl          : Adds supports for threading via CLI ( not supported by windows )
 - PHP PEAR support (dev-php/pear)

## Install

```
git clone --recursive https://github.com/phpipam/phpipam-agent/ phpipam-agent
```

Just run index.php script with discover or update as argument.

 - Make sure this client has read/write access to main phpipam database.
    ```SQL
    GRANT SELECT on `phpipam`.* TO 'username'@'hostname' identified by "password";
    GRANT INSERT,UPDATE on `phpipam`.`ipaddresses` TO 'username'@'hostname' identified by "password";
    GRANT UPDATE on phpipam.scanAgents TO 'username'@'hostname' identified by "password";
    ```
 - If you will remove inactive dhcp/autodiscovered also this is needed
    ```SQL
    GRANT DELETE on `phpipam`.`ipaddresses` TO 'username'@'hostname' identified by "password";
    ```

## Update

```
cd phpipam-agent
git pull
git submodule update --init --recursive
```

## MikroTik DHCP lease discovery

Instead of (or in addition to) ICMP scanning, the agent can populate phpipam directly from the
active DHCP leases on one or more MikroTik routers, pulled over the RouterOS binary API
(plaintext, default TCP port `8728`).

To enable it set the scan method to `mikrotik` and configure your routers in `config.php`:

```php
$config['method'] = "mikrotik";

$config['mikrotik']['routers'] = array(
    array('host' => "192.168.88.1", 'user' => "phpipam", 'pass' => "password", 'port' => 8728),
    // add more routers here ...
);
$config['mikrotik']['ping_check']  = true;                 // fping leases to set online/offline status
$config['mikrotik']['description'] = "MikroTik DHCP lease"; // "(dynamic)"/"(static)" is appended
$config['mikrotik']['tag_dynamic'] = 4;                    // phpipam IP tag for dynamic leases (DHCP)
$config['mikrotik']['tag_static']  = 2;                    // phpipam IP tag for static leases (Used)
```

How it works:

 - The agent connects to every configured router, reads `/ip/dhcp-server/lease`, and matches each
   lease into the subnets assigned to this scan agent.
 - Leases are tagged **dynamic** or **static** (RouterOS `dynamic` flag): the lease type is written
   into the address description and the matching phpipam IP tag is applied.
 - For **static** leases, if the RouterOS lease has a `comment` it is used as the address
   description instead of the default label; otherwise the default `"... (static)"` label is used.
 - The MAC address and host-name from the lease are stored on the address.
 - When `ping_check` is enabled each leased address is fping-checked to set its online/offline
   status, so `$config['pingpath']` must point at the `fping` binary. When disabled, the RouterOS
   lease `status` (`bound`) is used instead.
 - `discover` inserts new leases and updates existing addresses; `update` only refreshes addresses
   that already exist in the database.

Notes:

 - Only the plaintext binary API (port `8728`) is supported. Create a dedicated RouterOS user with
   API access and, ideally, read-only rights.
 - Both the modern (RouterOS >= 6.43) and legacy MD5 challenge-response logins are supported.
 - This method does not require the `pcntl` extension as it performs no ICMP thread forking.

## Scheduled scans
For scheduled scans you have to run a script from cron. Add something like the following to your cron to scan
every 15 minutes:

 ```
*/15 * * * * php /where/your/agent/index.php update
*/15 * * * * php /where/your/agent/index.php discover
```
## Contact
`miha.petkovsek@gmail.com`
