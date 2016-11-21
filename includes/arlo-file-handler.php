<?php

namespace Arlo;


class FileHandler {
	
	private $dir;
	private $filename;
	private $file;

	private $import_id;
	
	public function __construct($dir, $filename, $import_id = null) {
		$this->dir = $dir;
		$this->filename = $filename;

		$this->file = $this->dir . $this->filename;

		$this->import_id = $import_id;
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
			Logger::log_error('The file doesn\'t exist: ' . $file, $this->import_id);
		}
	}

	public function write_file($file = null, $data = null) {
		if (is_null($file)) 
			$file = $this->$file;

		$fp = fopen($file, 'w+');

		$success = fwrite($fp, $data);

		fclose($fp);

		return $success;
	}	

	public function read_file_as_json($file = null) {
		if (is_null($file)) 
			$file = $this->$file;

		return json_decode(mb_strcut(utf8_encode($this->read_file($file)), 6));
	}

	public function delete_file($file = null) {
		if (is_null($file)) 
			$file = $this->$file;

		if (!empty($file) && file_exists($file)) {
			unlink($file);
		}
	}
}