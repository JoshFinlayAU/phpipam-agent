<?php

/**
 *	MikroTik RouterOS API client
 *
 *	Minimal, dependency-free implementation of the RouterOS binary API
 *	protocol (plaintext, default TCP port 8728). Used by phpipam-agent to
 *	pull DHCP leases from MikroTik routers.
 *
 *	Protocol reference:
 *	https://help.mikrotik.com/docs/display/ROS/API
 *
 *	Only the small subset required by the agent is implemented:
 *		- connect / login (supports both the post-6.43 plain login and the
 *		  legacy MD5 challenge-response login)
 *		- sending a command sentence and reading the reply
 *		- a convenience helper to fetch /ip/dhcp-server/lease entries
 */

class RouterOS_API {

	/**
	 * router host / ip
	 * @var string
	 */
	private $host;

	/**
	 * api username
	 * @var string
	 */
	private $user;

	/**
	 * api password
	 * @var string
	 */
	private $pass;

	/**
	 * api port (default 8728 plaintext)
	 * @var int
	 */
	private $port;

	/**
	 * connect / read timeout in seconds
	 * @var int
	 */
	private $timeout;

	/**
	 * socket resource
	 * @var resource|false
	 */
	private $socket = false;

	/**
	 * __construct
	 *
	 * @param string $host
	 * @param string $user
	 * @param string $pass
	 * @param int    $port    (default: 8728)
	 * @param int    $timeout (default: 5)
	 */
	public function __construct ($host, $user, $pass, $port = 8728, $timeout = 5) {
		$this->host    = $host;
		$this->user    = $user;
		$this->pass    = $pass;
		$this->port    = is_numeric($port) ? (int) $port : 8728;
		$this->timeout = is_numeric($timeout) ? (int) $timeout : 5;
	}

	/**
	 * Opens TCP socket to the router.
	 *
	 * @return void
	 * @throws Exception on failure
	 */
	public function connect () {
		$errno  = 0;
		$errstr = '';
		$this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
		if ($this->socket === false) {
			throw new Exception("Unable to connect to MikroTik {$this->host}:{$this->port} - $errstr ($errno)");
		}
		// reads must not block forever
		stream_set_timeout($this->socket, $this->timeout);
	}

	/**
	 * Authenticates against the router.
	 *
	 *	RouterOS >= 6.43 accepts the password directly in the /login command.
	 *	Older versions reply with a challenge (=ret=) that must be answered
	 *	with an MD5 hash - both schemes are handled here.
	 *
	 * @return void
	 * @throws Exception on authentication failure
	 */
	public function login () {
		// post 6.43 style - send credentials directly
		$this->write_sentence(array('/login', '=name='.$this->user, '=password='.$this->pass));
		$response = $this->read_sentences();

		// legacy challenge-response (pre 6.43)
		$challenge = $this->extract_attribute($response, 'ret');
		if ($challenge !== null) {
			$bin_chal = pack('H*', $challenge);
			$md5      = md5(chr(0).$this->pass.$bin_chal, true);
			$this->write_sentence(array(
				'/login',
				'=name='.$this->user,
				'=response=00'.bin2hex($md5),
			));
			$response = $this->read_sentences();
		}

		if ($this->has_reply($response, '!trap') || $this->has_reply($response, '!fatal') || !$this->has_reply($response, '!done')) {
			$msg = $this->extract_attribute($response, 'message');
			throw new Exception("MikroTik login failed on {$this->host}".($msg ? ": $msg" : ""));
		}
	}

	/**
	 * Fetches all DHCP server leases.
	 *
	 *	Returns one associative array per lease, keys are the raw RouterOS
	 *	attribute names, e.g. 'address', 'mac-address', 'host-name',
	 *	'dynamic', 'status', 'server', 'comment'.
	 *
	 * @return array
	 */
	public function get_dhcp_leases () {
		$this->write_sentence(array('/ip/dhcp-server/lease/print'));
		$reply = $this->read_sentences();

		$leases = array();
		foreach ($reply as $sentence) {
			if (!isset($sentence['_tag']) || $sentence['_tag'] !== '!re') {
				continue;
			}
			unset($sentence['_tag']);
			$leases[] = $sentence;
		}
		return $leases;
	}

	/**
	 * Closes the socket.
	 *
	 * @return void
	 */
	public function disconnect () {
		if (is_resource($this->socket)) {
			// best effort logout / close
			@fclose($this->socket);
		}
		$this->socket = false;
	}

	/**
	 * @internal protocol helpers
	 * ---------------------------------
	 */

	/**
	 * Writes a full sentence (array of words) followed by the terminating
	 * zero-length word.
	 *
	 * @param array $words
	 * @return void
	 */
	private function write_sentence (array $words) {
		foreach ($words as $word) {
			$this->write_word($word);
		}
		// empty word terminates the sentence
		$this->write_word('');
	}

	/**
	 * Writes a single API word: length prefix followed by the raw bytes.
	 *
	 * @param string $word
	 * @return void
	 */
	private function write_word ($word) {
		$this->write_raw($this->encode_length(strlen($word)));
		if (strlen($word) > 0) {
			$this->write_raw($word);
		}
	}

	/**
	 * Reads sentences from the socket until a !done / !fatal reply is seen.
	 *
	 *	Each returned element is an associative array of the sentence
	 *	attributes plus a '_tag' key holding the reply type (!re, !done,
	 *	!trap, !fatal).
	 *
	 * @return array
	 */
	private function read_sentences () {
		$sentences = array();
		while (true) {
			$words = $this->read_sentence();
			if (empty($words)) {
				// stray empty sentence, keep reading
				continue;
			}
			$parsed = array('_tag' => $words[0]);
			for ($i = 1; $i < count($words); $i++) {
				$word = $words[$i];
				// attributes look like =key=value
				if (strlen($word) > 0 && $word[0] === '=') {
					$pos = strpos($word, '=', 1);
					if ($pos !== false) {
						$key = substr($word, 1, $pos - 1);
						$val = substr($word, $pos + 1);
						$parsed[$key] = $val;
					}
				}
			}
			$sentences[] = $parsed;

			if ($words[0] === '!done' || $words[0] === '!fatal') {
				break;
			}
		}
		return $sentences;
	}

	/**
	 * Reads a single sentence (list of words) up to the zero-length word.
	 *
	 * @return array
	 */
	private function read_sentence () {
		$words = array();
		while (true) {
			$length = $this->decode_length();
			if ($length === 0) {
				break;
			}
			$words[] = $this->read_raw($length);
		}
		return $words;
	}

	/**
	 * Encodes a word length according to the RouterOS API spec.
	 *
	 * @param int $length
	 * @return string raw bytes
	 */
	private function encode_length ($length) {
		if ($length < 0x80) {
			return chr($length);
		}
		if ($length < 0x4000) {
			$length |= 0x8000;
			return chr(($length >> 8) & 0xFF).chr($length & 0xFF);
		}
		if ($length < 0x200000) {
			$length |= 0xC00000;
			return chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
		}
		if ($length < 0x10000000) {
			$length |= 0xE0000000;
			return chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
		}
		// 5 byte form
		return chr(0xF0).chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
	}

	/**
	 * Reads and decodes a word length prefix from the socket.
	 *
	 * @return int
	 */
	private function decode_length () {
		$c = ord($this->read_raw(1));

		if (($c & 0x80) === 0x00) {
			return $c;
		}
		if (($c & 0xC0) === 0x80) {
			return (($c & ~0xC0) << 8) + ord($this->read_raw(1));
		}
		if (($c & 0xE0) === 0xC0) {
			$length = (($c & ~0xE0) << 16);
			$length += ord($this->read_raw(1)) << 8;
			$length += ord($this->read_raw(1));
			return $length;
		}
		if (($c & 0xF0) === 0xE0) {
			$length = (($c & ~0xF0) << 24);
			$length += ord($this->read_raw(1)) << 16;
			$length += ord($this->read_raw(1)) << 8;
			$length += ord($this->read_raw(1));
			return $length;
		}
		// 5 byte form, first byte is 0xF0, length is the next 4 bytes
		$length  = ord($this->read_raw(1)) << 24;
		$length += ord($this->read_raw(1)) << 16;
		$length += ord($this->read_raw(1)) << 8;
		$length += ord($this->read_raw(1));
		return $length;
	}

	/**
	 * Writes raw bytes to the socket.
	 *
	 * @param string $data
	 * @return void
	 * @throws Exception on write failure
	 */
	private function write_raw ($data) {
		$total = strlen($data);
		$sent  = 0;
		while ($sent < $total) {
			$written = @fwrite($this->socket, substr($data, $sent));
			if ($written === false || $written === 0) {
				throw new Exception("MikroTik API write failed on {$this->host}");
			}
			$sent += $written;
		}
	}

	/**
	 * Reads exactly $length bytes from the socket.
	 *
	 * @param int $length
	 * @return string
	 * @throws Exception on read failure / timeout
	 */
	private function read_raw ($length) {
		$data = '';
		while (strlen($data) < $length) {
			$chunk = fread($this->socket, $length - strlen($data));
			if ($chunk === false || $chunk === '') {
				$meta = stream_get_meta_data($this->socket);
				if (!empty($meta['timed_out'])) {
					throw new Exception("MikroTik API read timed out on {$this->host}");
				}
				throw new Exception("MikroTik API connection closed by {$this->host}");
			}
			$data .= $chunk;
		}
		return $data;
	}

	/**
	 * Returns true if any sentence in the reply has the given tag.
	 *
	 * @param array  $sentences
	 * @param string $tag
	 * @return bool
	 */
	private function has_reply ($sentences, $tag) {
		foreach ($sentences as $s) {
			if (isset($s['_tag']) && $s['_tag'] === $tag) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the first occurrence of an attribute across all sentences.
	 *
	 * @param array  $sentences
	 * @param string $key
	 * @return string|null
	 */
	private function extract_attribute ($sentences, $key) {
		foreach ($sentences as $s) {
			if (isset($s[$key])) {
				return $s[$key];
			}
		}
		return null;
	}
}
