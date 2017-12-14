<div class="arlo" id="arlo">
	[arlo_event_template_summary wrap="<p>%s</p>"]
	[arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration muted m-b-15"><i class="icons8-clock pull-left m-r-5"></i> %s</div>']

	<div class="arlo-event-template-filters collapse" id="filters">
		[arlo_event_filters filters="location,delivery"]
		[arlo_template_region_selector]
		[arlo_timezones wrap="<div class='arlo-timezone-toggle'>%s</div>"]
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
		[arlo_event_list_item show="4"]


          <div class="event m-b-10">

            <div class="drop-shadow bg-white padding-20 no-overflow full-width event-container" tabindex="-1">

              <div class="hidden visible-xs relative">
                <div class="date bg-primary text-white pull-left">
                  <div class="day">[arlo_event_start_date format="%e"]</div>
                  <div class="month">[arlo_event_start_date format="%b"]</div>
                </div>
                <div class="event-content md-p-l-70 md-p-b-10 md-p-t-10 md-p-r-0 xl-p-r-20 xl-p-l-20 lg-p-r-20 lg-p-l-20">
                  [arlo_event_location wrap='<h5 class="text-primary no-margin"><i class="icons8-marker"></i> %s</h5>']
                  <h5 class="no-margin">[arlo_event_duration wrap='%s,'] [arlo_event_start_date format="%I:%M %p"] - [arlo_event_end_date format="%I:%M %p"]</h5>
                </div>
                <a href="#" class="btn-expand mobile">
                  <i class="fa fa-angle-down" aria-hidden="true"></i>
                </a>
              </div>

              <div class="mobile-expanded-visible">
                <div class="visible-overflow xs-padding-20">
                  <div class="col-2-events">
                    <div class="col-1-events">
                      <h3 class='m-t-0 m-l-0 m-r-0 lh-1 m-b-10 hidden-xs'>[arlo_event_start_date format="%a %e %b"]</h3>
                      <div class="m-b-10">
                        <p class="expanded-visible no-margin normal-text visible-1-event hidden-xs">[arlo_event_start_date format="%e %B %Y"]</p>
                        <p class="expanded-visible no-margin normal-text visible-1-event hidden visible-xs">[arlo_event_start_date format="%e %B %Y"]</p>
                        <p class="truncate-1 no-margin time normal-text visible-1-event visible-2-events">[arlo_event_duration], [arlo_event_start_date format="%I:%M %p"] - [arlo_event_end_date format="%I:%M %p"]</p>
                      </div>
                    </div>

                    <div class="col-1-events last-col">
                       [arlo_event_location wrap='<div class="m-b-10">
                        <p class="location truncate-1 normal-text visible-1-event visible-2-events m-b-0"><i class="icons8-marker pull-left"></i> <span class="block m-l-25">%s</span></p>
                      </div>']
                    </div>
                  </div>


                  <div class="col-2-events col-1-events last-col xs-p-b-0 p-b-70">

                    [arlo_event_credits wrap='<div class="truncate-1 normal-text expanded-visible visible-1-event visible-2-events m-b-0 pd-points"><i class="icons8-prize pull-left"></i> <span class="block m-l-25">%s</span></div>']

			    	[arlo_event_provider wrap='<div class="expanded-visible provider">
                      <i class="icons8-building pull-left"></i> <span class="block m-l-25">Provided by %s</span>
                    </div>']

					[arlo_event_session_list_item]
					    <div class="arlo_session">
					        <h6>[arlo_event_name]</h6>
					        <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
					        [arlo_event_location]
					    </div>
					[/arlo_event_session_list_item]

                    [arlo_event_presenters wrap='<div class="presenters truncate-1 visible-1-event visible-2-events m-b-10">
                      <i class="icons8-user pull-left"></i> <span class="block m-l-25">Presented by %s</span>
                    </div>']

                    <div class="price expanded-hidden hidden-xs">[arlo_event_price]</div>

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

		[/arlo_event_list_item] 
		[/arlo_event_list]

		<div class="arlo-clear-both"></div>

		<div class="arlo-links">
			[arlo_event_template_register_interest]
			[arlo_suggest_datelocation wrap="<div class='arlo-suggest'>%s</div>"]
		</div>

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

			<!-- <li class="arlo-cf arlo-online-activity">
				[arlo_oa_reference_term wrap='<div class="arlo-event-headline">%s</div>']
				[arlo_oa_credits]
				
				[arlo_oa_delivery_description label="Delivery: " wrap='<div class="arlo-delivery-desc">%s</div>']
				[arlo_oa_offers]
				[arlo_oa_registration]
			</li>
			[arlo_oa_rich_snippet] -->

			[/arlo_oa_list_item]
		</div>
		[/arlo_oa_list]		
		
	</div>
	<div class="arlo-clear-both"></div>
	
	[arlo_content_field_item]
		[arlo_content_field_name wrap='<h3>%s</h3>']
		[arlo_content_field_text wrap='<p>%s</p>']
	[/arlo_content_field_item]

	[arlo_suggest_templates]
</div>
[arlo_powered_by]