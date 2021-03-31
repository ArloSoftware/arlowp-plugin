<?php

namespace Arlo\Importer;

use Arlo\Logger;

abstract class BaseImporter {
	public $iteration_finished = false;
    public $is_finished = false;
    public $iteration = 0;
	public $task_id;

    protected $id;
    protected $importer;
	protected $dbl;
	protected $message_handler;
	protected $api_client;
	protected $scheduler;
	protected $importing_parts;

    protected $import_id;

    protected $data;
    protected $table_name;
	
   
    abstract protected function save_entity($item);

    public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
        $this->importer = $importer;
		$this->dbl = $dbl;
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
		if(!empty($advertised_offer) && is_array($advertised_offer)) {
			$template_id = (intval($template_id) > 0 ? $template_id : null);
			$event_id = (intval($event_id) > 0 ? $event_id : null);
			$oa_id = (intval($oa_id) > 0 ? $oa_id : null);
		
			//$offers = array_reverse($advertised_offer);
			foreach($advertised_offer as $key => $offer) {
				$query = $this->dbl->query( $this->dbl->prepare( 
					"INSERT INTO " . $this->dbl->prefix . "arlo_offers 
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
					throw new \Exception('SQL error: ' . $this->dbl->last_error );
				}
			}
		}	
	}

	protected function save_tags($tags = [], $id, $type = '') {
		switch ($type) {
			case "template":
				$field = "et_id";
				$table_name = $this->dbl->prefix . "arlo_eventtemplates_tags";			
			break;		
			case "event":
				$field = "e_id";
				$table_name = $this->dbl->prefix . "arlo_events_tags";			
			break;
			case "oa":
				$field = "oa_id";
				$table_name = $this->dbl->prefix . "arlo_onlineactivities_tags";
			break;			
			default: 
			 	throw new \Exception('Tag type failed: ' . $type);
			break;		
		}
		
		if (isset($tags) && is_array($tags)) {
			$exisiting_tags = [];
			$sql = "
			SELECT 
				id, 
				tag
			FROM
				" . $this->dbl->prefix . "arlo_tags 
			WHERE 
				tag IN ('" . implode("', '", esc_sql($tags)) . "')
			AND
				import_id = " . $this->import_id . "
			";

			$rows = $this->dbl->get_results($sql, ARRAY_A);
			foreach ($rows as $row) {
				$exisiting_tags[$row['tag']] = $row['id'];
			}
			unset($rows);		
			
			foreach ($tags as $tag) {
				if (empty($exisiting_tags[$tag])) {
					$query = $this->dbl->query( $this->dbl->prepare( 
						"INSERT INTO " . $this->dbl->prefix . "arlo_tags
						(tag, import_id) 
						VALUES ( %s, %d ) 
						", 
						$tag,
						$this->import_id
					) );
												
					if ($query === false) {
						throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
					} else {
						$exisiting_tags[$tag] = $this->dbl->insert_id;
					}
				}
										
				if (!empty($exisiting_tags[$tag])) {
					$query = $this->dbl->query( $this->dbl->prepare( 
						"INSERT INTO {$table_name}
						(" . $field . ", tag_id, import_id) 
						VALUES ( %d, %d, %d ) 
						", 
						$id,
						$exisiting_tags[$tag],
						$this->import_id
					) );
					
					if ($query === false) {
						throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
					}
				} else {
					throw new \Exception('Couldn\'t find tag: ' . $tag );
				}					
			}
		}
	}
}