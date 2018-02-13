<div class="arlo" id="arlo">
	[arlo_template_region_selector]
	[arlo_upcoming_event_filters filtertext='Filter' resettext="Reset" filters='category,month,location']
	
	<ul class="arlo-list upcoming">
		[arlo_upcoming_list_item limit="10"]
		
		<li class="arlo-cf">
			<div class="arlo-cal">
				<div class="arlo-month arlo-background-color1">[arlo_event_start_date format="%b"]</div>
				<div class="arlo-day">[arlo_event_start_date format="%d"]</div>
			</div>
			<div class="arlo-event-details">
				<h3 class="arlo-event-name">[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a></h3>
				<div class="arlo-event-subheading">
					[arlo_event_location label="" wrap="<span class='arlo-event-location'><i class='fa fa-map-marker'></i>%s</span>"]
					
					<span class="arlo-event-time">
						<i class="fa fa-clock-o"></i>[arlo_event_duration_description format="%I:%M %p"]
					</span>
					[arlo_event_provider wrap='<span class="arlo-event-provider">Provided by %s</span>']
					[arlo_event_session_list_item]
					   <div class="arlo_session">
					       <h6>[arlo_event_name]</h6>
					       <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
					       [arlo_event_location]
					   </div>
					[/arlo_event_session_list_item]
				</div>

				<div class="arlo-event-notice">
					[arlo_event_notice]
				</div>

				<div class="arlo-template-summary">
					[arlo_event_template_summary]
				</div>

				<div class="arlo-offer">
					[arlo_event_offers]
				</div>

				<div class="arlo-buttons">
					[arlo_event_template_permalink wrap='<a href="%s" class="button arlo-more-information arlo-button arlo-background-color1">']More Information</a>
					[arlo_event_registration]
				</div>
			</div>
		</li>	
		[arlo_event_rich_snippet]
		[/arlo_upcoming_list_item]
	</ul>

	<div class="arlo-clear-both"></div>
    
	[arlo_no_event_text]
	
	<div class="arlo-pagination">
	    [arlo_upcoming_list_pagination limit="10"]
	</div>
	[arlo_powered_by]
	
</div>
