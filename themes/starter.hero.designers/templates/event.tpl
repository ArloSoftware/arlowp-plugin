<div class="arlo" id="arlo">

	<div class="arlo-hero-container">
		[arlo_event_template_hero_image]

		<div class="arlo-event-template-summary-and-al p-t-30 p-b-30">
			[arlo_event_template_credits text='<div class="pd-points-circle pd-points-circle-inverted sm-m-b-10 m-b-15 m-l-10 m-r-10"><span class="points">{%points%}</span><span class="points-label">{%label%}</span></div>' wrap='<div class="arlo-event-template-credits-list hidden-sm">%s</div>']
			[arlo_event_template_summary wrap="<p>%s</p>"]
			[arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration banner-item muted sm-m-b-10 m-b-15 inline m-r-20"><i class="icons8-clock pull-left m-r-5"></i> %s</div>']
			[arlo_event_template_advertised_price wrap='<div class="arlo-advertised-price banner-item muted sm-m-b-10 m-b-15 inline m-r-20"><i class="icons8-us-dollar pull-left m-r-5"></i> %s</div>']
			[arlo_event_template_advertised_presenters wrap='<div class="arlo-advertised-presenters banner-item muted sm-m-b-10 m-b-15 inline m-r-20"><i class="icons8-user pull-left m-r-5"></i> %s</div>']
			[arlo_event_template_credits text='<div class="banner-item muted sm-m-b-10 m-b-15 inline m-r-20"><i class="icons8-prize pull-left m-r-5"></i><span class="">{%points%} {%label%}</span></div>' wrap='<div class="arlo-event-template-credits-list-lined hidden"> %s</div>']
		</div>
	</div>

	[arlo_template_region_selector wrap="<div class='arlo-region-selector'>%s</div>"]

	<div class="arlo-event-template-filters clearfix collapse" id="filters">
		[arlo_event_filters filters="location,delivery"]
		[arlo_timezones wrap="<div class='arlo-timezone-selector'><p>Live online events</p>%s</div>"]
	</div>

	<button data-toggle="collapse" data-target="#filters" class="btn form-control full-width m-b-20 filter-toggle collapsed">
      <div class="display-filters">
        Display filters
      </div>

      <div class="hide-filters">
        Hide filters
      </div>
    </button>

    <div class="clearfix row events-4 arlo-template-events-list p-b-10">

		[arlo_event_list]
		[arlo_event_list_item show="4" within_ul="false"]


          <div class="event m-b-10">

            <div class="drop-shadow bg-white padding-20 no-overflow full-width event-container" tabindex="-1">

              <div class="hidden visible-xs relative">
                <div class="date bg-primary text-white pull-left">
                  <div class="day">[arlo_event_start_date format="%e"]</div>
                  <div class="month">[arlo_event_start_date format="%b"]</div>
                </div>
                <div class="event-content md-p-l-70 md-p-b-10 md-p-t-10 md-p-r-10 xl-p-r-20 xl-p-l-20 lg-p-r-20 lg-p-l-20">
                  [arlo_event_location wrap='<h5 class="text-primary no-margin"><i class="icons8-marker"></i> %s</h5>']
                  <h5 class="no-margin">[arlo_event_duration wrap='%s,'] [arlo_event_start_date format="%I:%M %p"] - [arlo_event_end_date format="%I:%M %p"]</h5>
                </div>
                <a href="#" class="btn-expand mobile">
                  <i class="fa fa-angle-down" aria-hidden="true"></i>
                </a>
              </div>

              <div class="mobile-expanded-visible">
                <div class="visible-overflow xs-padding-20">

                  [arlo_event_isfull output="Sold out" wrap='<div class="arlo-event-label arlo-full text-white bg-danger">%s</div>']
                  [arlo_event_offers_hasdiscount output="Discount" wrap='<div class="arlo-event-label arlo-full text-white bg-success">%s</div>']

                  <div class="col-2-events">
                    <div class="col-1-events">
                      <h3 class='m-t-0 m-l-0 m-r-0 lh-1 m-b-10 hidden-xs'>[arlo_event_start_date format="%a %e %b"]</h3>
                      <div class="m-b-10">
                        <p class="expanded-visible no-margin normal-text visible-1-event hidden-xs">[arlo_event_dates startdateformat="%e %B %Y" enddateformat="%e %B %Y"]</p>
                        <p class="expanded-visible no-margin normal-text visible-1-event hidden visible-xs">[arlo_event_dates startdateformat="%e %B %Y" enddateformat="%e %B %Y"]</p>
                        <p class="truncate-1 no-margin time normal-text visible-1-event visible-2-events">[arlo_event_duration], [arlo_event_start_date format="%I:%M %p"] - [arlo_event_end_date format="%I:%M %p"]</p>
                      </div>
                    </div>

                    <div class="col-1-events last-col">
                       [arlo_event_location wrap='<div class="m-b-10">
                        <p class="location truncate-1 normal-text visible-1-event visible-2-events m-b-0"><i class="icons8-marker pull-left"></i> <span class="block m-l-25">%s</span></p>
                      </div>']

                       <div class="expanded-visible location-full">
                          [arlo_venue_permalink wrap='<a href="%s">'][arlo_venue_name]</a>
                          [arlo_venue_address wrap='<div class="muted">%s</div>']
                        </div>

                    </div>
                  </div>


                  <div class="col-2-events col-1-events last-col xs-p-b-0 p-b-70">

                    [arlo_event_credits wrap='<div class="truncate-1 normal-text expanded-visible visible-1-event visible-2-events m-b-0 pd-points"><i class="icons8-prize pull-left"></i> <span class="block m-l-25">%s</span></div>']

                    [arlo_event_provider wrap='<div class="expanded-visible provider">
                      <i class="icons8-building pull-left"></i> <span class="block m-l-25">Provided by %s</span>
                    </div>']

										[arlo_event_session_list_item layout="popup" wrap='<div class="m-b-10"><i class="icons8-clock pull-left m-r-5"></i> %s</div>']

											<div class="row m-b-10 b-b b-grey p-b-10 m-l-15 m-r-15">
												<div class="col-xs-12 padding-0">
													<strong class="m-b-5 block">[arlo_event_name]</strong>
												</div>

												<div class="col-xs-12 col-sm-7 p-l-0 xs-p-r-0">
													<div class="row row-fix">
														<div class="col-xs-12 col-sm-6 col-md-5 xs-p-l-0 xs-p-r-0">
															<div class="session-timespan m-b-5">[arlo_event_start_date format="%H:%M"] - [arlo_event_end_date format="%H:%M"]</div>
															<div class="muted m-b-5">[arlo_event_duration]</div>
															<div class="location block m-b-5 hidden-xs"><a href="#"><i class="icons8-marker pull-left"></i> <span class="block m-l-25">[arlo_event_location]</span></a></div>
															<div class="visible-xs text-primary">[arlo_event_location]</div>
														</div>

														<div class="col-xs-12 col-sm-6 col-md-4 xs-p-l-0 xs-p-r-0">
															<div class="m-b-5 xs-p-t-15">[arlo_event_offers]</div>
														</div>

														<div class="col-xs-12 col-sm-6 col-md-3 xs-p-l-0 xs-p-r-0">
															[arlo_event_notice wrap='<p class="normal-text m-b-10 expanded-visible">%s</p>']
															<div class="m-b-5 xs-p-t-15">
																[arlo_event_presenters layout="list"]
															</div>
														</div>
													</div>
												</div>

												<div class="col-xs-12 col-sm-5 p-r-0 xs-p-l-0">
													<div class="xs-p-t-15">[arlo_event_summary]</div>
												</div>

												<div class="col-xs-12 session-full">
													<strong class="m-b-5 block">[arlo_event_isfull output="Sold out"]</strong>
												</div>
											</div>

										[/arlo_event_session_list_item]

                    [arlo_event_presenters wrap='<div class="presenters truncate-1 visible-1-event visible-2-events m-b-10">
                      <i class="icons8-user pull-left"></i> <span class="block m-l-25">Presented by %s</span>
                    </div>']

                    <div class="price expanded-hidden hidden-xs">[arlo_event_price order="event" showfrom="false"]</div>

                    <div class="price expanded-visible">
                      [arlo_event_offers]
                    </div>

                    <div class="buttons xs-m-t-10">
                      [arlo_event_registration]
                    </div>
                  </div>
                </div>
              </div>

              <a class="btn-expand desktop">
                <i class="fa fa-angle-down" aria-hidden="true"></i>
              </a>

            </div>
          </div>
          
        [arlo_event_rich_snippet]

		[/arlo_event_list_item] 
		[/arlo_event_list]

		<div class="arlo-clear-both"></div>

		<div class="arlo-private-links">
			[arlo_event_template_register_private_interest]
			[arlo_suggest_private_datelocation]
		</div>

		<div class="arlo-links">
			[arlo_event_template_register_interest]
			[arlo_suggest_datelocation wrap="<div class='arlo-suggest'>%s</div>"]
		</div>
	</div>
	<div class="arlo-clear-both"></div>
	
		[arlo_oa_list]
        <div class="clearfix row p-b-20">
			[arlo_oa_list_item]

				<div class="drop-shadow bg-white padding-20 sm-p-b-20 no-overflow full-width online-activity relative xs-p-l-0 xs-p-r-0 xs-p-t-0 xs-p-b-0 m-b-10">
		            <div class="relative">
		              	<div class="hidden visible-xs relative">
			                <div class="date bg-primary text-white pull-left p-t-15">
			                  <i class="icons8-start"></i>
			                </div>
			                <div class="event-content md-p-l-70 md-p-b-10 md-p-t-10 md-p-r-0 xl-p-r-20 xl-p-l-20 lg-p-r-20 lg-p-l-20">
			                	[arlo_oa_reference_term wrap='<h5 class="text-primary">%s</h5>']
			                </div>
						</div>

						<div class="hidden-xs">
							<div class="col-xs-12 col-md-3 md-p-l-0 md-p-r-0">
			                	[arlo_oa_reference_term wrap='<h3 class="no-margin">%s</h3>']
							</div>
							<div class="col-xs-8 col-md-6 b-l b-grey md-p-l-0">
							  <p>Complete online at your own pace (Self-paced)</p>
							  <p>[arlo_oa_offers]</p>
							</div>
							<div class="col-xs-4 col-md-3 text-right md-p-l-0 p-r-0">
							  <a class="btn btn-secondary sm-m-b-10" href="#">Purchase</a>
							</div>
						</div>

						<a class="btn-expand mobile">
							<i class="fa fa-angle-down" aria-hidden="true"></i>
						</a>
	            	</div>

		            <div class="mobile-expanded-visible">
		              <div class="sm-padding-15">
		              [arlo_oa_delivery_description wrap='<p class="normal-text m-b-0">%s</p>']
		              <p class="normal-text">[arlo_oa_offers]</p>
		              [arlo_oa_registration]
		              </div>
		            </div>

            	</div>

			[/arlo_oa_list_item]
		</div>
		[/arlo_oa_list]		

	[arlo_content_field_item]
		[arlo_content_field_name wrap='<h3>%s</h3>']
		[arlo_content_field_text wrap='<p>%s</p>']
	[/arlo_content_field_item]

	[arlo_suggest_templates]
</div>
[arlo_powered_by]