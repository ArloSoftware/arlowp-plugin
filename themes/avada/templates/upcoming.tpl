<div class="arlo" id="arlo">
	[arlo_template_region_selector]
	[arlo_upcoming_event_filters filtertext='Filter' resettext="Reset" filters='category,month,location']
	<ul class="arlo-list upcoming">
		[arlo_upcoming_list_item limit="20"]
		[arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider arlo-color1 arlo-font2">%s</li>']
		<li class="arlo-cf">
			<div class="arlo-cal arlo-background-color2 arlo-border-color1 arlo-font2">
				[arlo_event_start_date format="%d" wrap='<div class="arlo-day">%s</div>']
				[arlo_event_start_date format="%b" wrap='<div class="arlo-month">%s</div>']
			</div>

			<div class="arlo-event-info">
			    <div class="arlo-event-details">
				<h4 class=" arlo-event-name">[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a></h4>
				     <div class="arlo-event-subhead">
					<div class="arlo-event-time"><i class="fa fa-clock-o" aria-hidden="true"></i> [arlo_event_duration], [arlo_event_start_date format="%I:%M %p"] - [arlo_event_end_date format="%I:%M %p"]</div>
					[arlo_event_location label="" wrap="<div class='arlo-event-location'><i class='fa fa-map-marker' aria-hidden='true'></i> %s</div>"]
					[arlo_event_session_description wrap='<div class="arlo-event-session-description"><i class="fa fa-clock-o" aria-hidden="true"></i> %s</div>']
					[arlo_event_presenters wrap='<div class="arlo-event-presenters"><i class="fa fa-user" aria-hidden="true"></i> %s</div>']
				   </div>
				</div>
				<div class="arlo-template-summary">
					[arlo_event_template_summary]
				</div>
				[arlo_upcoming_offer wrap='<div class="arlo-offers arlo-color1">%s</div>']
				[arlo_event_registration]
			    </div>
		</li>	
		[/arlo_upcoming_list_item]
	</ul>
	<div class="arlo-clear-both"></div>
	[arlo_no_event_text]
	[arlo_upcoming_list_pagination limit="20" wrap='<div class="arlo-pagination">%s</div>']
</div>