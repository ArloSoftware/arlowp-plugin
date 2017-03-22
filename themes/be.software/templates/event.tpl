<div class="arlo" id="arlo">
<div class="arlo-banner arlo-background-color1">
  [arlo_event_template_name wrap="<h1>%s</h1>"]
  <img src="/wp-content/plugins/arlo-training-and-event-management-system/themes/be.software/images/separator.png" alt="" width="137" height="8">

</div>

[arlo_template_region_selector]
[arlo_timezones wrap="<div class='arlo-timezone-toggle'>%s</div>"]
[arlo_event_template_summary wrap="<p class='arlo-event-template-summary'>%s</p>"]

<ul class="arlo-list arlo-show-more events" data-show="3" data-show-text="Show more">
    [arlo_event_list]
        [arlo_event_list_item show="4"]
        <li class="arlo-cf">
            [arlo_event_start_date format="D j M" wrap='<h4 class="arlo-item-header">%s</h4>']
            <div class="arlo-event-time">[arlo_event_start_date format="D g:i A"] - [arlo_event_end_date format="D g:i A"]</div>
            [arlo_event_session_list_item]
                <div class="arlo_session">
                    <h6>[arlo_event_name]</h6>
                    <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
                    [arlo_event_location]
                </div>
            [/arlo_event_session_list_item]
            [arlo_event_location wrap="<div class='arlo-event-location'>%s</div>"]
            <div class="arlo-presenters">[arlo_event_presenters wrap="Presented by %s"]</div>
            [arlo_event_offers wrap='<div class="arlo-color1">%s</div>']
            [arlo_event_registration]
        </li>
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
</div>