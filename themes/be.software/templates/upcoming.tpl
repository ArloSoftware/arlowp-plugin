<div class="arlo-boxed arlo">
	[arlo_template_region_selector]
	[arlo_upcoming_event_filters filtertext='Filter' resettext="Reset" filters='category,month,location']
	
	<ul class="arlo-list upcoming">
		[arlo_upcoming_list_item limit="10"]
		
		<li class="arlo-cf">
			<div class="arlo-cal">
				<div class="arlo-month">[arlo_event_start_date format="%b"]</div>
				<div class="arlo-day">[arlo_event_start_date format="%e"]</div>
			</div>
			<div class="arlo-event-details">
				<h4 class="arlo-ellipsis">[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a></h4>
				<div class="arlo-event-subheading">
					[arlo_event_location label="" wrap="<span class='arlo-event-location'><i class='fa fa-map-marker'></i>%s</span>"]
					
					<span class="arlo-event-time">
						<i class="fa fa-clock-o"></i>[arlo_event_duration], [arlo_event_start_date format="%I:%M %p"] - [arlo_event_end_date format="%I:%M %p"]
					</span>
					[arlo_event_session_description wrap='<span class="arlo-event-session-description">%s</span>']
				</div>

				<div class="arlo-template-summary">
					[arlo_event_template_summary]
				</div>

				<div class="arlo-offer">
					[arlo_upcoming_offer]
				</div>

				<div class="arlo-buttons">
					[arlo_event_template_permalink wrap='<a href="%s" class="button arlo-more-information">']More Information</a>
					[arlo_event_registration]
				</div>
			</div>
		</li>	
		[/arlo_upcoming_list_item]
	</ul>

	<div class="arlo-clear-both"></div>
    
	[arlo_no_event_text]
	
	<div class="arlo-pagination">
	    [arlo_upcoming_list_pagination limit="10"]
	</div>
</div>