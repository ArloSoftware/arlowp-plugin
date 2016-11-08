<?php

namespace Arlo\Importer;

error_reporting(E_ALL);
ini_set('display_error', 1);

use Arlo\Singleton;

class Importer extends Singleton {
	public static $filename;
	public static $is_finished = false;
	
	protected static $dir;
	protected static $import_id;
	protected static $plugin;
	protected static $data_json;
	protected static $wpdb;
	

	private $import_timezones;
	private $import_presenters;
	private $import_venues;
	private $import_event_templates;
	private $import_events;
	private $import_onlineactivities;
	private $import_categories;
	private $import_finish;
		
	public $import_tasks = [
				'import_timezones' => "Importing time zones",
				'import_presenters' => "Importing presenters",
				'import_event_templates' => "Importing event templates",
				'import_events' => "Importing events",
				'import_onlineactivities' => "Importing online activities",
				'import_venues' => "Importing venues",
				'import_categories' => "Importing categories",
				'import_finish' => "Finalize the import",
			];	

	public $current_task;
	public $current_task_num;
	public $current_task_desc = '';

	public function __construct($plugin) {
		global $wpdb;
		
		self::$wpdb = &$wpdb; 
		self::$dir = trailingslashit(plugin_dir_path( __FILE__ )).'../../import/';
		self::$filename = 'data'; //TODO: Change it
		self::$plugin = $plugin;

		$this->import_timezones = new Timezones();
		$this->import_presenters = new Presenters();
		$this->import_venues = new Venues();
		$this->import_event_templates = new Templates();
		$this->import_events = new Events();
		$this->import_onlineactivities = new OnlineActivities();
		$this->import_categories = new Categories();
		$this->import_finish = new Finish();
	}

	public function set_import_id($import_id) {
		self::$import_id = $import_id;
	}

	public function set_current_task($task_step_num) {
		$task_keys = array_keys($this->import_tasks);
		if (array_key_exists($task_step_num, $task_keys)) {
			$this->current_task_num = $task_step_num;
			$this->current_task = $task_keys[$task_step_num];
			$this->current_task_desc = $this->import_tasks[$this->current_task];
		} else {
			throw new \Exception('Invalid task step num: ' . $task_step_num);
		}
	}

	private function get_data_json() {
		$filename = self::$dir . self::$filename . '.json';
		
		if (is_null(self::$data_json)) {
			self::$data_json = json_decode(mb_strcut(utf8_encode($this->read_file($filename)), 6));
		}
	}

	protected function read_file($filename) {
		if (!empty($filename) && file_exists($filename)) {
			$fp = fopen($filename, 'r');
			$content = fread($fp, filesize($filename));
			fclose($fp);

			return $content;
		} else {
			self::$plugin->add_log('The file doesn\'t exist: ' . self::$filename, self::$import_id);
			throw new \Exception('The file doesn\'t exist:' . self::$filename);
		}
	}

	protected function write_file($filename, $data) {
		$fp = fopen($file, 'w+');

		$success = fwrite($fp, $data);

		fclose($fp);

		return $success;
	}

	public function decrypt() {
		$filename = self::$dir . self::$filename;
		$json = json_decode(utf8_encode($this->read_file($filename)));

		if (isset($json->__encrypted__)) {
			try {
				self::$data_json = Crypto::decrypt($json->__encrypted__);
				
				$this->write_file($filename . '.json', self::$data_json);
			} catch (\Exception $e) {
				self::$plugin->add_log('Couldn\'t decrypt the file: ' . $e->getMessage(), self::$import_id);				
			}
		}

		return false;

		unset($json);
	}

	public function run() {
		if (isset($this->import_tasks[$this->current_task])) {
			self::$plugin->add_log($this->current_task_num  . 'Import subtask started: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc, self::$import_id);
			
			$this->run_import_task($this->current_task);

			self::$plugin->add_log($this->current_task_num  . 'Import subtask ended: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc, self::$import_id);
		} 
	}

	private function run_import_task($import_task) {
		$this->get_data_json(); 
		
		if (!empty(self::$data_json)) {
			$this->$import_task->import();
		}
	}

	protected function save_advertised_offer($advertised_offer, $region = '', $template_id = null, $event_id = null, $oa_id = null) {
		if(!empty($advertised_offer) && is_array($advertised_offer)) {
			$template_id = (intval($template_id) > 0 ? $template_id : null);
			$event_id = (intval($event_id) > 0 ? $event_id : null);
			$oa_id = (intval($oa_id) > 0 ? $oa_id : null);
		
			$offers = array_reverse($advertised_offer);
			foreach($offers as $key => $offer) {
				$query = self::$wpdb->query( self::$wpdb->prepare( 
					"INSERT INTO " . self::$wpdb->prefix . "arlo_offers 
					(o_arlo_id, et_id, e_id, oa_id, o_label, o_isdiscountoffer, o_currencycode, o_offeramounttaxexclusive, o_offeramounttaxinclusive, o_formattedamounttaxexclusive, o_formattedamounttaxinclusive, o_taxrateshortcode, o_taxratename, o_taxratepercentage, o_message, o_order, o_replaces, o_region, active) 
					VALUES ( %d, %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s ) 
					", 
					$offer->OfferID + 1,
				    $template_id,
					$event_id,
					$oa_id,
					@$offer->Label,
					@$offer->IsDiscountOffer,
					@$offer->OfferAmount->CurrencyCode,
					@$offer->OfferAmount->AmountTaxExclusive,
					@$offer->OfferAmount->AmountTaxInclusive,
					@$offer->OfferAmount->FormattedAmountTaxExclusive,
					@$offer->OfferAmount->FormattedAmountTaxInclusive,
					@$offer->OfferAmount->TaxRate->ShortName,
					@$offer->OfferAmount->TaxRate->Name,
					@$offer->OfferAmount->TaxRate->RatePercent,
					@$offer->Message,
					$key+1,
					(isset($offer->ReplacesOfferID)) ? $offer->ReplacesOfferID+1 : null,
					(!empty($region) ? $region : 'NULL'),
					self::$import_id
				) );
				
				if ($query === false) {
					self::$plugin->add_log('SQL error: ' . self::$wpdb->last_error . ' ' .self::$wpdb->last_query, self::$import_id);
					throw new Exception('Database insert failed: ' . self::$wpdb->prefix . 'arlo_offers');
				}
			}
		}	
	}

	protected function save_tags($tags = [], $id, $type = '') {
		switch ($type) {
			case "template":
				$field = "et_id";
				$table_name = self::$wpdb->prefix . "arlo_eventtemplates_tags";			
			break;		
			case "event":
				$field = "e_id";
				$table_name = self::$wpdb->prefix . "arlo_events_tags";			
			break;
			case "oa":
				$field = "oa_id";
				$table_name = self::$wpdb->prefix . "arlo_onlineactivities_tags";
			break;			
			default: 
				throw new Exception('Tag type failed: ' . $type);
			break;		
		}

		
		
		if (isset($tags) && is_array($tags)) {
			$exisiting_tags = [];
			$sql = "
			SELECT 
				id, 
				tag
			FROM
				" . self::$wpdb->prefix . "arlo_tags 
			WHERE 
				tag IN ('" . implode("', '", $tags) . "')
			AND
				active = " . self::$import_id . "
			";

			$rows = self::$wpdb->get_results($sql, ARRAY_A);
			foreach ($rows as $row) {
				$exisiting_tags[$row['tag']] = $row['id'];
			}
			unset($rows);
			
			foreach ($tags as $tag) {
				if (empty($exisiting_tags[$tag])) {
					$query = self::$wpdb->query( self::$wpdb->prepare( 
						"INSERT INTO " . self::$wpdb->prefix . "arlo_tags
						(tag, active) 
						VALUES ( %s, %s ) 
						", 
						$tag,
						self::$import_id
					) );
												
					if ($query === false) {
						self::$plugin->add_log('SQL error: ' . self::$wpdb->last_error . ' ' .self::$wpdb->last_query, self::$import_id);
						throw new Exception('Database insert failed: ' . self::$wpdb->prefix . 'arlo_tags ' . $type );
					} else {
						$exisiting_tags[$tag] = self::$wpdb->insert_id;
					}
				}
										
				if (!empty($exisiting_tags[$tag])) {
					$query = self::$wpdb->query( self::$wpdb->prepare( 
						"INSERT INTO {$table_name}
						(" . $field . ", tag_id, active) 
						VALUES ( %d, %d, %s ) 
						", 
						$id,
						$exisiting_tags[$tag],
						self::$import_id
					) );
					
					if ($query === false) {
						self::$plugin->add_log('SQL error: ' . self::$wpdb->last_error . ' ' .self::$wpdb->last_query, self::$import_id);
						throw new Exception('Database insert failed: ' . $table_name );
					}
				} else {
					throw new Exception('Couldn\'t find tag: ' . $tag );
				}
			}
		}
	}
}