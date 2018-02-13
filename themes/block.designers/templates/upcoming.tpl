<div class="arlo" id="arlo">
	[arlo_template_region_selector]
	[arlo_upcoming_event_filters filtertext='Filter' resettext="Reset" filters='category,month,location']
	<ul class="arlo-list upcoming">
		[arlo_upcoming_list_item limit="20"]
		[arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider"><h3 class="arlo-color2">%s</h3></li>']
		<li class="arlo-cf">
			<div class="arlo-cal">
				[arlo_event_start_date format="%b" wrap='<div class="arlo-month arlo-background-color1 arlo-contrast-color">%s</div>']
				[arlo_event_start_date format="j" wrap='<div class="arlo-day arlo-color2">%s</div>']
			</div>

			<div class="arlo-event-info">
			    <div class="arlo-event-details">
				<h4 class="arlo-event-name">[arlo_event_template_permalink wrap='<a href="%s" class="arlo-color2">'][arlo_event_template_name]</a></h4>
				     <div class="arlo-event-subhead">
					[arlo_event_location label="" wrap="<div class='arlo-event-location'><i class='icons8-marker-filled'></i> %s</div>"]
					[arlo_event_presenters wrap='<div class="arlo-event-presenters"><i class="icons8-gender-neutral-user-filled"></i> %s</div>']
					[arlo_event_provider wrap='<div class="arlo-event-provider">Provided by %s</div>']
					[arlo_event_session_list_item]
					   <div class="arlo_session">
					       <h6>[arlo_event_name]</h6>
					       <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
					       [arlo_event_location]
					   </div>
					[/arlo_event_session_list_item]
				   </div>
				</div>
				<div class="arlo-event-time">[arlo_event_duration_description format="%I:%M %p"]</div>
				<div class="arlo-event-notice">[arlo_event_notice]</div>
				<div class="arlo-template-summary">
					[arlo_event_template_summary]
				</div>
				[arlo_event_offers wrap='<div class="arlo-offers">%s</div>']
				[arlo_event_registration]
			    </div>
		</li>	
		[arlo_event_rich_snippet]
		[/arlo_upcoming_list_item]
	</ul>
	<div class="arlo-clear-both"></div>
	[arlo_no_event_text]
	[arlo_upcoming_list_pagination limit="20" wrap='<div class="arlo-pagination">%s</div>']
	[arlo_powered_by]
	
</div>
