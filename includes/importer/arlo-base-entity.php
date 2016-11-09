<?php

namespace Arlo\Importer;

abstract class BaseEntity {
    public $is_finished = false;
    public $iterator = 0;

    protected $id;
    protected $wpdb;
    protected $plugin;
    protected $importer;
    protected $import_id;

    protected $data;
    protected $table_name;
   
    abstract protected function save_entity($item);

    public function __construct($plugin, $importer, $data, $iterator = 0) {
        global $wpdb;

        $this->plugin = $plugin;
        $this->importer = $importer;
        $this->wpdb = &$wpdb;
        $this->import_id = $importer->import_id;
        $this->data = $data;
        $this->iterator = $iterator;
    }

	public function import() {
		if (!empty($this->data) && is_array($this->data)) {
            
            $count = count($this->data);
            
            for($i = $this->iterator; $i < $count; $i++) {
				$this->save_entity($this->data[$i]);
				
				if (!$this->importer->check_viable_execution_environment()) {
					$this->iterator = $i;
					break;
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
		
			$offers = array_reverse($advertised_offer);
			foreach($offers as $key => $offer) {
				$query = $this->wpdb->query( $this->wpdb->prepare( 
					"INSERT INTO " . $this->wpdb->prefix . "arlo_offers 
					(o_arlo_id, et_id, e_id, oa_id, o_label, o_isdiscountoffer, o_currencycode, o_offeramounttaxexclusive, o_offeramounttaxinclusive, o_formattedamounttaxexclusive, o_formattedamounttaxinclusive, o_taxrateshortcode, o_taxratename, o_taxratepercentage, o_message, o_order, o_replaces, o_region, import_id) 
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
					(!empty($region) ? $region : ''),
					$this->import_id
				) );
				
				if ($query === false) {
					Logger::log('SQL error: ' . $this->wpdb->last_error . ' ' . $this->wpdb->last_query, $this->import_id, null, false , true);
				}
			}
		}	
	}

	protected function save_tags($tags = [], $id, $type = '') {
		switch ($type) {
			case "template":
				$field = "et_id";
				$table_name = $this->wpdb->prefix . "arlo_eventtemplates_tags";			
			break;		
			case "event":
				$field = "e_id";
				$table_name = $this->wpdb->prefix . "arlo_events_tags";			
			break;
			case "oa":
				$field = "oa_id";
				$table_name = $this->wpdb->prefix . "arlo_onlineactivities_tags";
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
				" . $this->wpdb->prefix . "arlo_tags 
			WHERE 
				tag IN ('" . implode("', '", $tags) . "')
			AND
				import_id = " . $this->import_id . "
			";

			$rows = $this->wpdb->get_results($sql, ARRAY_A);
			foreach ($rows as $row) {
				$exisiting_tags[$row['tag']] = $row['id'];
			}
			unset($rows);
			
			foreach ($tags as $tag) {
				if (empty($exisiting_tags[$tag])) {
					$query = $this->wpdb->query( $this->wpdb->prepare( 
						"INSERT INTO " . $this->wpdb->prefix . "arlo_tags
						(tag, import_id) 
						VALUES ( %s, %s ) 
						", 
						$tag,
						$this->import_id
					) );
												
					if ($query === false) {
						Logger::log('SQL error: ' . $this->wpdb->last_error . ' ' .$this->wpdb->last_query, $this->import_id, null, false , true);
					} else {
						$exisiting_tags[$tag] = $this->wpdb->insert_id;
					}
				}
										
				if (!empty($exisiting_tags[$tag])) {
					$query = $this->wpdb->query( $this->wpdb->prepare( 
						"INSERT INTO {$table_name}
						(" . $field . ", tag_id, import_id) 
						VALUES ( %d, %d, %s ) 
						", 
						$id,
						$exisiting_tags[$tag],
						$this->import_id
					) );
					
					if ($query === false) {
						Logger::log('SQL error: ' . $this->wpdb->last_error . ' ' .$this->wpdb->last_query, $this->import_id, null, false , true);
					}
				} else {
					throw new Exception('Couldn\'t find tag: ' . $tag );
				}
			}
		}
	}
}