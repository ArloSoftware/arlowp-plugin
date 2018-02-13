<div class="arlo-boxed">
	[arlo_template_region_selector]
	[arlo_upcoming_event_filters filtertext='Filter' resettext="Reset" filters='category,month,location']
	<ul class="arlo-list upcoming">
		[arlo_upcoming_list_item limit="20"]
		[arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider">%s</li>']
		<li class="arlo-cf arlo-event">
			<h4>[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a></h4>
			<div class="arlo-cal">
				[arlo_event_start_date format="%a %d %b" wrap='<div class="arlo-date">%s</div>']
			</div>
			<div class="arlo-event-details">
				<span class="arlo-event-time">[arlo_event_duration_description format="%I:%M %p"]</span>
				[arlo_event_location label="" wrap="<div class='arlo-event-location'>%s</div>"]
				[arlo_event_presenters label="Presenters: " wrap='<div class="arlo-event-presenters">%s</div>']
				[arlo_event_provider label="Provider: " wrap='<div class="arlo-event-provider">Provided by %s</div>']
				[arlo_event_template_advertised_duration label="Duration: " wrap='<div class="arlo-event-duration">%s</div>']
				
                [arlo_event_session_list_item]
                   <div class="arlo_session">
                       <h6>[arlo_event_name]</h6>
                       <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
                       [arlo_event_location]
                   </div>
                [/arlo_event_session_list_item]

			</div>
			<h5>Summary</h5>
			<div class="arlo-template-summary">
				[arlo_event_template_summary]
			</div>
			[arlo_event_notice label="Special note: " wrap='<div class="arlo-event-notice">%s</div>']
            [arlo_event_offers wrap='<div class="arlo-event-offers">%s</div>']
			[arlo_event_registration]
		</li>	
		[arlo_event_rich_snippet]
		[/arlo_upcoming_list_item]
	</ul>
	<div class="arlo-clear-both"></div>
	[arlo_no_event_text]
	[arlo_upcoming_list_pagination limit="20"]
</div>

[arlo_powered_by]