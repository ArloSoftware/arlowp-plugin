<div class="arlo" id="arlo">

    <div class="arlo-event-template-filters">
        [arlo_template_region_selector wrap="<div class='arlo-region-selector'>%s</div>"]
        [arlo_event_filters]
        [arlo_timezones wrap='<div class="arlo-timezone-selector"><p>Live online events</p>%s</div>']
    </div>

    [arlo_event_template_summary wrap='<p class="arlo-event-template-summary">%s</p>']

    <ul class="arlo-list arlo-show-more events">
        [arlo_event_list]
            [arlo_event_list_item show="4"]
            <li class="arlo-cf arlo-event">

                <div class="arlo-cal">
                    <div class="arlo-day">[arlo_event_start_date format="%d"]</div>
                    <div class="arlo-month">[arlo_event_start_date format="%b"]</div>
                </div>

                <div class="arlo-event-details">
                    <div class="arlo-event-info">
                    <div class="arlo-event-time"><i class="icons8-clock"></i>[arlo_event_duration_description format="D g:i A"]</div>

                        [arlo_event_location wrap='<div class="arlo-event-location"><i class="icons8-marker"></i>%s</div>']
                        [arlo_event_presenters wrap='<div class="arlo-event-presenters"><i class="icons8-gender-neutral-user"></i>%s</div>']

                        [arlo_event_session_list_item]
                            <div class="arlo_session">
                                <h6>[arlo_event_name]</h6>
                                <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
                                [arlo_event_location]
                            </div>
                        [/arlo_event_session_list_item]
                        [arlo_event_notice wrap='<div class="arlo-event-notice">%s</div>']
                        [arlo_event_offers wrap='<div>%s</div>']
                    </div>
                    <div class="arlo-registration">
                        [arlo_event_registration]
                    </div>
                </div>
            </li>
            [arlo_event_rich_snippet]
            [/arlo_event_list_item]
        [/arlo_event_list]
    </ul>

    [arlo_event_template_register_interest]
    [arlo_suggest_datelocation wrap='<div class="arlo-suggest arlo-background-color3">%s</div>']


    <ul class="arlo-list online-activities">
    [arlo_oa_list]
        [arlo_oa_list_item]
        <li class="arlo-cf arlo-onlineactivity">
            <div class="arlo-oa-info arlo-left">
                [arlo_oa_reference_term wrap="<strong class='arlo-color2'>%s</strong>"]
                [arlo_oa_credits]
                [arlo_oa_delivery_description wrap='<div class="arlo-delivery-desc">%s</div>']
                [arlo_oa_offers wrap='<div class="arlo-color2">%s</div>']
            </div>
            <div class="arlo-registration arlo-right">
                [arlo_oa_registration]
            </div>
        </li>
        [arlo_oa_rich_snippet]
        [/arlo_oa_list_item]
    [/arlo_oa_list]
    </ul>

    <div class="arlo-content-fields">
        [arlo_content_field_item]
            <div class="arlo-content-field">[arlo_content_field_name wrap='<h4>%s</h4>']
            <div class="arlo-content-field-description">[arlo_content_field_text wrap='<p>%s</p>']</div>
        </div>
        [/arlo_content_field_item]
    </div>

    <div class="arlo-suggest-template-container">
        <h4>Similar courses</h4>
        <ul class="arlo-suggest-templates">
            [arlo_suggest_templates limit="4"]
            <li class="arlo-cf">
                <div class="arlo-suggest-template-name">
                    [arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a>
                </div>
                <div class="arlo-suggest-template-event-link">[arlo_event_next_running]</div>
            </li>
            [/arlo_suggest_templates]
        </ul>
    </div>
    [arlo_powered_by]
</div>
