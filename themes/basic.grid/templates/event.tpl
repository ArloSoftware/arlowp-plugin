<div class="arlo-boxed">
	[arlo_template_region_selector]
	[arlo_event_filters]
	[arlo_timezones wrap="<div class='arlo-timezone-selector'><p>Live online events</p>%s</div>"]
	<div class="arlo-template-head">
		[arlo_event_template_summary wrap="<p>%s</p>"]
		[arlo_event_template_advertised_duration wrap="<p>%s</p>"]
	</div>

	<ul class="arlo-list arlo-show-more events" data-show="3" data-show-text="Show more">
		[arlo_event_list]
		[arlo_event_list_item show="3"]
		<li class="arlo-cf">
			<div class="arlo-cal">
				[arlo_event_start_date format="%a %d %b" wrap='<div class="arlo-event-headline">%s</div>']
	
			</div>
			<div class="arlo-event-details">
				<div class="arlo-event-time">[arlo_event_duration_description format="%I:%M %p"]</div>
				[arlo_event_location label="" wrap="<div class='arlo-event-location'>%s</div>"]
				[arlo_event_session_list_item wrap="<div class='arlo-sessions'>%s</div>"]
					<div class="arlo_session">
						<h6>[arlo_event_name]</h6>
						<div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
						[arlo_event_location]
					</div>
				[/arlo_event_session_list_item]
				[arlo_event_presenters label="Presenters: "]
				[arlo_event_credits]
				[arlo_event_tags layout="list" label="Tags: " wrap='<div class="arlo-tags">%s</div>']
			</div>
			[arlo_event_notice label="Special note: " wrap='<div class="arlo-event-notice">%s</div>']
			[arlo_event_offers]
			[arlo_event_registration]
		</li>
		[arlo_event_rich_snippet]
		[/arlo_event_list_item]
		[/arlo_event_list]	
		
	</ul>
	<div class="arlo-clear-both"></div>
	[arlo_event_template_register_interest]
	[arlo_suggest_datelocation wrap="<div class='arlo-suggest'>%s</div>"]
	
	<ul class="arlo-list arlo-show-more template-online-activities">
		[arlo_oa_list]
			[arlo_oa_list_item]
			<li class="arlo-cf arlo-online-activity">
				[arlo_oa_reference_term wrap='<div class="arlo-event-headline">%s</div>']
				[arlo_oa_name]
				[arlo_oa_credits]
				
				[arlo_oa_delivery_description label="Delivery: " wrap='<div class="arlo-delivery-desc">%s</div>']
				[arlo_oa_offers]
				[arlo_oa_registration]
			</li>
			[arlo_oa_rich_snippet]
			[/arlo_oa_list_item]
		[/arlo_oa_list]	
	</ul>

	[arlo_content_field_item]
		[arlo_content_field_name wrap='<h5>%s</h5>']
		[arlo_content_field_text wrap='<p>%s</p>']
	[/arlo_content_field_item]
</div>
[arlo_powered_by]