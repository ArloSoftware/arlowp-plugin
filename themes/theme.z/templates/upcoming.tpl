<div id="arloapp" class="arlo-desktop">
	<div class="arlo-wrapper arlo-upcoming">
		[arlo_template_region_selector]
		<div class="arlo-filter-mobile arlo-filter-upcoming-mobile">
			<span>Filters</span>
			<div class="arlo-icon-wrapper nomargin large outline gray arlo-upcoming-filter-icon"><i class="fa-solid fa-sliders"></i><i class="fa-solid fa-xmark"></i></div>
		</div>
		<div class="arlo-upcoming-filter-wrapper">
			<div class="arlo-upcoming-filter">
				<h2>Filter courses</h2>
				[arlo_upcoming_event_filters showlabel="true" filtertext='Filter' labeltype="v1" filters='month,delivery,category,location,templatetag']
			</div>
		</div>
		<div class="arlo-upcoming-result">
			<div class="arlo-upcoming-result-events" role="list">
				[arlo_upcoming_list_item limit="6" noevent_before='<div class="arlo-not-found">' noevent_after='<a href="">Enquire about a course you are interested in.</a></div>']
					<div class="arlo-upcoming-result-events-item" role="listitem">
						<div class="arlo-upcoming-result-events-item-date">
							<div>[arlo_event_dates hidesameentry="true" connectwith="-" startdateformat="%b" enddateformat="%b"]</div>
							<div>[arlo_event_dates hidesameentry="true" connectwith="-" startdateformat="%d" enddateformat="%d"]</div>
							<div>[arlo_event_dates hidesameentry="true" connectwith="-" startdateformat="%Y" enddateformat="%Y"]</div>
						</div>
						<div class="arlo-upcoming-result-events-item-title">
							<h2>[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a></h2>
							[arlo_event_provider wrap='<p>Provided by %s</p>']
						</div>
						<div class="arlo-upcoming-result-events-item-info">
							<div class="arlo-upcoming-result-events-item-info-basic">
								[arlo_condition_return param='Online' shortcode_value='arlo_event_location' cond="contains" return_content="true"]
									[arlo_event_location wrap='<div><i class="fa-solid fa-desktop"></i>%s</div>']
								[/arlo_condition_return]
								[arlo_condition_return param='Online' shortcode_value='arlo_event_location' cond="ncontains" return_content="true"]
									[arlo_event_location wrap='<div><i class="fa-solid fa-location-dot"></i>%s</div>']
								[/arlo_condition_return]

								<div><i class="fa-solid fa-clock"></i>[arlo_event_duration showweek="true" wrap="%s " after=","][arlo_event_start_date format="%I:%M %p"] - [arlo_event_end_date format="%I:%M %p"]</div>
								[arlo_event_session_list_item layout="popup" wrap='<div aria-label="event sessions arlo-first" ><i class="fa-solid fa-clock"></i><span>%s</span></div>']
									<div class="session-row">
									<div class="session-name">
										<strong class="m-b-5 block">{%index%}. [arlo_event_name]</strong>
									</div>
									<div class="session-time">
										[arlo_event_start_date format="l" wrap='<div><i class="fa-solid fa-calendar"></i>%s'][arlo_event_start_date format=", %d %b %Y" wrap='%s</div>']
                        				[arlo_event_start_date format="%H:%M" wrap='<div><i class="fa-solid fa-clock"></i>%s'][arlo_event_end_date format="%H:%M" wrap='- %s</div>']
										[arlo_event_location wrap='<div><i class="fa-solid fa-location-dot"></i>%s</div>']
										[arlo_event_presenters wrap='<div><i class="fa-solid fa-user"></i>%s</a></div>']
									</div>
									</div>
								[/arlo_event_session_list_item]
								[arlo_event_presenters wrap='<div><i class="fa-solid fa-user"></i><div>%s</div></div>']
								[arlo_event_offers wrap='<div class="arlo-offers"><i class="fa-solid fa-tag"></i>%s</div>']
							</div>
							<div class="arlo-upcoming-result-events-item-info-more">
								<p>[arlo_event_template_summary]</p>
								<div class="arlo-upcoming-result-events-item-info-more-buttons">
									[arlo_event_template_permalink wrap='<a href="%s" class="arlo-button arlo-gray" role="button">Learn more</a>']
									[arlo_event_registration class="arlo-button" fullclass="arlo-button outline"]
								</div>
							</div>
						</div>
					</div>
					[arlo_event_rich_snippet]
				[/arlo_upcoming_list_item]
				
			</div>
			<div class="arlo-align-center-row">
				<button class="arlo-button arlo-gray arlo-btn-more-uce">Show more</button>
			</div>
		</div>
		
		
		
	</div>
	<div class="arlo-pager">
		[arlo_upcoming_list_pagination limit="6" wrap='<div class="arlo-pagination">%s</div>']
	</div>
	<div class="arlo-wrapper arlo-upcoming-bottom">
		[arlo_powered_by]
	</div>
</div>