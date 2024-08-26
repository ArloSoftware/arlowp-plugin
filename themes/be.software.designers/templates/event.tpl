<div class="arlo" id="arlo">

    <div class="arlo-event-template-filters">
        [arlo_template_region_selector wrap="<div class='arlo-region-selector'>%s</div>"]
        [arlo_event_filters]
        [arlo_timezones wrap="<div class='arlo-timezone-selector'><p>Live online events</p>%s</div>"]
    </div>

    [arlo_event_template_summary wrap="<p class='arlo-event-template-summary'>%s</p>"]

    <ul class="arlo-list arlo-show-more events" data-show="3" data-show-text="Show more">
        [arlo_event_list]
            [arlo_event_list_item show="4"]
            <li class="arlo-cf">
                [arlo_event_start_date format="D j M" wrap='<h4 class="arlo-item-header">%s</h4>']
                <div class="arlo-event-time">[arlo_event_duration_description format="D g:i A"]</div>
                [arlo_event_session_list_item]
                    <div class="arlo_session">
                        <h6>[arlo_event_name]</h6>
                        <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
                        [arlo_event_location]
                    </div>
                [/arlo_event_session_list_item]
                [arlo_event_location wrap="<div class='arlo-event-location'>%s</div>"]
                <div class="arlo-presenters">[arlo_event_presenters wrap="Presented by %s"]</div>
                [arlo_event_notice wrap='<div class="arlo-event-notice">%s</div>']
                [arlo_event_offers wrap='<div class="arlo-color1">%s</div>']
                [arlo_event_registration]
            </li>
            [arlo_event_rich_snippet]
            [/arlo_event_list_item]
        [/arlo_event_list]
    </ul>

    [arlo_event_template_register_interest]
    [arlo_suggest_datelocation wrap="<div class='arlo-suggest arlo-background-color3'>%s</div>"]


    <ul class="arlo-list online-activities">
    [arlo_oa_list]
        [arlo_oa_list_item]
        <li class="arlo-cf arlo-onlineactivity">
            <div class="arlo-left">
                [arlo_oa_name wrap='<h4>%s</h4>']
                [arlo_oa_credits]
                
                [arlo_oa_delivery_description wrap='<div class="arlo-delivery-desc">%s</div>']
                [arlo_oa_offers wrap='<div class="arlo-color1">%s</div>']
            </div>
            <div class="arlo-right">
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
        <h3>Similar courses</h3>
        <table class="arlo-suggest-templates">
            [arlo_suggest_templates limit="4"]
            <tr>
                <td class="arlo-suggest-template-name">
                    [arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a>
                </td>
                <td class="arlo-suggest-template-event-link">[arlo_event_next_running buttonclass="arlo-button"]</td>
            </tr>
            [/arlo_suggest_templates]
        </table>
    </div>
    [arlo_powered_by]
</div>