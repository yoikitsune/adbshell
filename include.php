<?php
	class Handler {
		public $path;
		public $device;

		public $history;

		function __construct () {
			$this->path = dirname (__FILE__);
			$this->device = $_SERVER ["device_name"];
			$this->history = new History ($this);
		}

		function exec ($command, &$result = array (), &$return = 0) {
			exec ($command, $result, $return);
			if ($return != 0) {
				echo "Command error\n";
				print_r ($result);
				$result = null;
				return false;
			}
			return true;
		}

		function pmGetEnabledPackages (&$packages) {
			return $this->exec ("adb shell pm list packages -e | cut -c 9-", $packages);
		}

		function pmGetPackages (&$packages, $args = "") {
			return $this->exec ("adb shell pm list packages $args | cut -c 9-", $packages);
		}
		
		function pmDisablePackage ($name, $save = true) {
			if ($this->exec ("adb shell pm disable-user --user 0 $name")) {
				$this->history->add ($name, $save);
				return true;
			}
			return false;
		}
		
		function pmDisablePackages ($packages) {
			foreach ($packages as $p) {
				echo "Disabling $p ...";
				if ($this->pmDisablePackage ($p, false))
					echo "OK\n";
			}
			$this->history->save ();
		}

		function pmEnablePackage ($name, $save = true) {
			if ($this->exec ("adb shell pm enable --user 0 $name")) {
				$this->history->del ($name, $save);
				return true;
			}
			return false;
		}

		function pmEnablePackages ($packages) {
			foreach ($packages as $p) {
				echo "Enabling $p ...";
				if ($this->pmEnablePackage ($p, false))
					echo "OK\n";
			}
			$this->history->save ();
		}
		
		function aaptGetApplicationLabel ($path) {
			$aapt = "/data/local/tmp/aapt-arm-pie";
			exec ('adb shell "'.$aapt.' dump badging '.$path.'" 2>/dev/null | grep -E "application-label:"', $label);
			if (isset ($label[0]))
				return substr ($label[0],19,-1);
			else
				return "";
		}
	}
	$handler = new Handler ();
	
	class History {
		private $handler;
		private $file;
		private $content = array ();

		function __construct ($handler) {
			$this->handler = $handler;
			$this->file = $handler->path. "/history/". $handler->device;
			if (file_exists ($this->file))
				$this->content = file ($this->file, FILE_IGNORE_NEW_LINES);
			else
				file_put_contents ($this->file, "");
		}
		
		function get () {
			return array_slice($this->content,0);
		}
		function add ($name, $save = true) {
			if (array_search ($name, $this->content) === false) {
				$this->content [] = $name;
				$save && $this->save ();
			}
		}
		
		function del ($name, $save = true) {
			if (($pos = array_search ($name, $this->content)) !== false) {
				array_splice ($this->content, $pos, 1);
				$save && $this->save ();
			}
		}

		function restore () {
			$this->handler->pmEnablePackages ($this->content);
		}

		function save () {
			return file_put_contents ($this->file, implode ("\n", $this->content));
		}
	}
?>
