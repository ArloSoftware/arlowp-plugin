<div class="arlo" id="arlo">
    <div class="arlo-template-head">
        [arlo_event_template_summary wrap="<p>%s</p>"]
    </div>

    [arlo_template_region_selector wrap="<div class='arlo-region-selector'>%s</div>"]
    [arlo_event_filters buttonclass="arlo-button"]
    [arlo_timezones wrap="<div class='arlo-timezone-selector'><p>Live online events</p>%s</div>"]

    <ul class="arlo-list arlo-show-more events" data-show="3" data-show-text="Show more">
        [arlo_event_list]
        [arlo_event_list_item show="3"]
        <li class="arlo-cf arlo-event">
          <div class="arlo-date arlo-background-color1 arlo-contrast-color">
                [arlo_event_start_date format="j F"]
          </div>
          <div class="arlo-event-duration arlo-background-color2 arlo-contrast-color">[arlo_event_duration_description format="%I:%M %p"]</div>
          <div class="arlo-event-details">
            <div class="arlo-event-time">
                <div class="arlo-sessions-wrapper">
                [arlo_event_session_list_item wrap='<i class="icons8-clock"></i> %s']
                    <div class="arlo_session">
                        <h6>[arlo_event_name]</h6>
                        <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
                        [arlo_event_location]
                    </div>
                [/arlo_event_session_list_item]
                </div>
            </div>
            [arlo_event_location label="" wrap="<div class='arlo-event-location'><i class='icons8-marker-filled'></i> %s</div>"]
            [arlo_event_presenters wrap='<div class="arlo-event-presenters">%s</div>']
            [arlo_event_notice wrap='<div class="arlo-event-notice">%s</div>']
            [arlo_event_credits wrap='<div class="arlo-event-credits">%s</div>']
            [arlo_event_offers]
            [arlo_event_tags layout="list"]
            [arlo_event_registration]
           </div>
        </li>
        [arlo_event_rich_snippet]
        [/arlo_event_list_item]
        [/arlo_event_list]
    </ul>

    [arlo_event_template_register_interest]
    [arlo_suggest_datelocation wrap="<div class='arlo-suggest'>%s</div>"]
    
    <ul class="arlo-list arlo-show-more template-online-activities">
        [arlo_oa_list]
            [arlo_oa_list_item]
            <li class="arlo-cf arlo-online-activity">
                [arlo_oa_reference_term wrap="<h3>%s</h3>"]
                [arlo_oa_credits]
                
                [arlo_oa_delivery_description wrap='<div class="arlo-delivery-desc">%s</div>']
                [arlo_oa_offers]
                [arlo_oa_registration class="arlo-button"]
            </li>
            [arlo_oa_rich_snippet]
            [/arlo_oa_list_item]
        [/arlo_oa_list]     
    </ul>

    <div class="arlo-content-fields">
    [arlo_content_field_item]
       <div class="arlo-content-field">
          [arlo_content_field_name wrap='<h3>%s</h3>']
          [arlo_content_field_text wrap='<p>%s</p>']
       </div>
    [/arlo_content_field_item]
    </div>

    <div class="arlo-suggest-template-container">
        <h3>Similar courses</h3>
        <table class="arlo-suggest-templates">
            [arlo_suggest_templates limit="4"]
            <tr>
                <td class="arlo-suggest-template-name">
                    [arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a>
                </td>
                <td class="arlo-suggest-template-event-link">[arlo_event_next_running buttonclass="button"]</td>
            </tr>
            [/arlo_suggest_templates]
        </table>
    </div>
    [arlo_powered_by]
</div>