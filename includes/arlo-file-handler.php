<?php

namespace Arlo;


class FileHandler {
	
	private $dir;
	private $filename;
	private $file;

	private $import_id;
	
	public function __construct($dir, $filename) {
		$this->dir = $dir;
		$this->filename = $filename;

		$this->file = $this->dir . $this->filename;
	}

	public function read_file($file = null) {
		if (is_null($file)) 
			$file = $this->$file;

		if (!empty($file) && file_exists($file)) {
			$fp = fopen($file, 'r');
			$content = fread($fp, filesize($file));
			fclose($fp);
			
			return $content;
		} else {
			throw new \Exception('The file doesn\'t exist: ' . $file);
		}
	}

	public function write_file($file = null, $data = null) {
		if (is_null($file)) 
			$file = $this->$file;

		$fp = fopen($file, 'w+');
		$success = fwrite($fp, $data);
		fclose($fp);

		if (false === $success) {
			error_log("Cannot write to file $file. Please check write permissions.");
		}

		return $success;
	}	

	public function read_file_as_json($file = null) {
		if (is_null($file)) 
			$file = $this->$file;

		$json = json_decode($this->read_file($file));

		if (is_null($json)) {
			throw new \Exception("JSON ERROR: " . json_last_error_msg());
		}

		return $json;
	}

	public function delete_file($file = null) {
		if (is_null($file)) 
			$file = $this->$file;

		if (!empty($file) && file_exists($file)) {
			unlink($file);
		}
	}
}