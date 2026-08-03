<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;

abstract class BaseImporter {
	public $iteration_finished = false;
    public $is_finished = false;
    public $iteration = 0;
	public $task_id;

    protected $id;
    protected $importer;
	protected $message_handler;
	protected $api_client;
	protected $scheduler;
	protected $importing_parts;

    protected $import_id;

    protected $data;

    abstract protected function save_entity($item);

    public function __construct($importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
        $this->importer = $importer;
		$this->message_handler = $message_handler;
		$this->api_client = $api_client;
		$this->scheduler = $scheduler;
		$this->importing_parts = $importing_parts;

        $this->import_id = $importer->import_id;
        $this->data = $data;
        $this->iteration = $iteration;
    }

	public function get_state() {
		return null;
	}

	public function run() {
		if (!empty($this->data) && is_array($this->data)) {
            $count = count($this->data);

            for($i = $this->iteration; $i < $count; $i++) {
				if (isset($this->data[$i])) {
					$this->save_entity($this->data[$i]);
					if (!$this->importer->check_viable_execution_environment()) {
						$this->iteration = $i;
						break;
					}
				}
			}

            if ($i >= $count) {
                $this->is_finished = true;
            }
		}
	}    

	protected function save_advertised_offer($advertised_offer, $region = '', $template_id = null, $event_id = null, $oa_id = null) {
		global $wpdb;
		if(!empty($advertised_offer) && is_array($advertised_offer)) {
			$template_id = (intval($template_id) > 0 ? $template_id : null);
			$event_id = (intval($event_id) > 0 ? $event_id : null);
			$oa_id = (intval($oa_id) > 0 ? $oa_id : null);
		
			//$offers = array_reverse($advertised_offer);
			foreach($advertised_offer as $key => $offer) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for insert operation in data import process.
				$query = $wpdb->query( $wpdb->prepare("INSERT INTO {$wpdb->prefix}arlo_offers 
					(o_arlo_id, et_id, e_id, oa_id, o_label, o_isdiscountoffer, o_currencycode, o_offeramounttaxexclusive, o_offeramounttaxinclusive, o_formattedamounttaxexclusive, o_formattedamounttaxinclusive, o_taxrateshortcode, o_taxratename, o_taxratepercentage, o_message, o_order, o_replaces, o_region, import_id) 
					VALUES ( %d, %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s ) 
					", 
					$offer->OfferID + 1,
				    $template_id,
					$event_id,
					$oa_id,
					!empty($offer->Label) ? $offer->Label : null,
					!empty($offer->IsDiscountOffer), // bool
					!empty($offer->OfferAmount) && !empty($offer->OfferAmount->CurrencyCode) ? $offer->OfferAmount->CurrencyCode : null,
					!empty($offer->OfferAmount) && !empty($offer->OfferAmount->AmountTaxExclusive) ? $offer->OfferAmount->AmountTaxExclusive : null,
					!empty($offer->OfferAmount) && !empty($offer->OfferAmount->AmountTaxInclusive) ? $offer->OfferAmount->AmountTaxInclusive : null,
					!empty($offer->OfferAmount) && !empty($offer->OfferAmount->FormattedAmountTaxExclusive) ? $offer->OfferAmount->FormattedAmountTaxExclusive : null,
					!empty($offer->OfferAmount) && !empty($offer->OfferAmount->FormattedAmountTaxInclusive) ? $offer->OfferAmount->FormattedAmountTaxInclusive : null,
					!empty($offer->OfferAmount) && !empty($offer->OfferAmount->TaxRate) && !empty($offer->OfferAmount->TaxRate->ShortName) ? $offer->OfferAmount->TaxRate->ShortName : null,
					!empty($offer->OfferAmount) && !empty($offer->OfferAmount->TaxRate) && !empty($offer->OfferAmount->TaxRate->Name) ? $offer->OfferAmount->TaxRate->Name : null,
					!empty($offer->OfferAmount) && !empty($offer->OfferAmount->TaxRate) && !empty($offer->OfferAmount->TaxRate->RatePercent) ? $offer->OfferAmount->TaxRate->RatePercent : null,
					!empty($offer->Message) ? $offer->Message : null,
					$key+1,
					(isset($offer->ReplacesOfferID)) ? $offer->ReplacesOfferID+1 : null,
					(!empty($region) ? $region : ''),
					$this->import_id
				) );
				
				if ($query === false) {
					throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
				}
			}
		}	
	}

	protected function save_tags($tags, $id, $type = '') {
		global $wpdb;
		switch ($type) {
			case "template":
				$field = "et_id";
				$table_name = $wpdb->prefix . "arlo_eventtemplates_tags";			
			break;		
			case "event":
				$field = "e_id";
				$table_name = $wpdb->prefix . "arlo_events_tags";			
			break;
			case "oa":
				$field = "oa_id";
				$table_name = $wpdb->prefix . "arlo_onlineactivities_tags";
			break;			
			default: 
				throw new \Exception('Tag type failed: ' . $type); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			break;		
		}
		
		if (isset($tags) && is_array($tags)) {
			$exisiting_tags = [];
			$sql = "
			SELECT 
				id, 
				tag
			FROM
				" . $wpdb->prefix . "arlo_tags 
			WHERE 
				tag IN (" . implode(',', array_map(function() {return "%s";}, $tags)) . ")
			AND
				import_id = %d
			";
			
			$parameter = array_merge([], $tags);
			$parameter[] = $this->import_id;

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The SQL statement is dynamically constructed and parameters are prepared here. Direct database query is required for custom table. Do not need cache for import process.
			$rows = $wpdb->get_results($wpdb->prepare($sql, $parameter), ARRAY_A);
			foreach ($rows as $row) {
				$exisiting_tags[$row['tag']] = $row['id'];
			}
			unset($rows);		
			
			foreach ($tags as $tag) {
				if (empty($exisiting_tags[$tag])) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for import process.
					$query = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}arlo_tags
						(tag, import_id) 
						VALUES ( %s, %d ) 
						", 
						$tag,
						$this->import_id
					) );
												
					if ($query === false) {
						throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
					} else {
						$exisiting_tags[$tag] = $wpdb->insert_id;
					}
				}
										
				if (!empty($exisiting_tags[$tag])) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct database query is required. Table name and field are safe static string. The SQL statement is dynamically constructed and parameters are prepared here. Do not need cache for import process.
					$query = $wpdb->query( $wpdb->prepare("INSERT INTO {$table_name} (" . $field . ", tag_id, import_id) VALUES ( %d, %d, %d ) ", 
						$id,
						$exisiting_tags[$tag],
						$this->import_id
					) );
					
					if ($query === false) {
						throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
					}
				} else {
					throw new \Exception('Couldn\'t find tag: ' . $tag); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
				}					
			}
		}
	}
}