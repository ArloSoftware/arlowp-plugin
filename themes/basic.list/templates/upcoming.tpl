[arlo_template_region_selector]
[arlo_upcoming_event_filters filtertext='Filter' resettext="Reset" filters='category,month,location,delivery']

<ul class="arlo-list upcoming">
    [arlo_upcoming_list_item limit="50"]
        [arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider">%s</li>']
	
        <li class="arlo-cf">
            <h4>[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a></h4>
            <div class="arlo-left">
                <div>[arlo_event_start_date format="%d %b %Y"]</div>
                
                [arlo_label wrap="<span class='arlo-event-time'>%s</span>"]
                    [arlo_event_duration_description format="%a %I:%M %p"]
                [/arlo_label]

                [arlo_event_location label="Location: " wrap="<div class='arlo-event-location'>%s</div>"]
                [arlo_event_provider label="Provider: " wrap="<div class='arlo-event-provider'>Provided by %s</div>"]
                [arlo_event_delivery label="Delivery: " wrap="<div class='arlo-event-delivery'>%s</div>"]
                [arlo_event_template_advertised_duration label="Duration: "]
                [arlo_event_offers wrap='<div class="arlo-event-offers">%s</div>']
                [arlo_event_notice label="Special note: " wrap='<div class="arlo-event-notice">%s</div>']
                [arlo_event_session_list_item]
                   <div class="arlo_session">
                       <h6>[arlo_event_name]</h6>
                       <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
                       [arlo_event_location]
                   </div>
                [/arlo_event_session_list_item]
            </div>
            <div class="arlo-right">
                [arlo_event_registration]
            </div>

            [arlo_event_template_summary label='<h5>Summary</h5>' wrap='<p class="arlo-clear-both">%s</p>']
        </li>
        [arlo_event_rich_snippet]
    [/arlo_upcoming_list_item]
</ul>

[arlo_no_event_text]
[arlo_upcoming_list_pagination limit="50"]
[arlo_powered_by]
