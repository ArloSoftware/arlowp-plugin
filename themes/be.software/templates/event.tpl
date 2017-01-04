<div class="arlo-banner-outer arlo-full-width">
	<div class="arlo-banner-inner">
        [arlo_event_template_name]
		<div>
			<img class="scale-with-grid" src="http://be.software.wpdemo.arlo.co/wp-content/uploads/2015/07/home_software_subheader_sep.png" alt="" width="137" height="8">
		</div>
	</div>
</div>

[arlo_template_region_selector]
[arlo_timezones wrap="<div class='arlo-timezone-toggle'>%s</div>"]
[arlo_event_template_summary wrap="<p class='arlo-summary'>%s</p>"]

<ul class="arlo-list arlo-show-more events" data-show="3" data-show-text="Show more">
    [arlo_event_list]
        [arlo_event_list_item show="4"]
        <li class="arlo-cf">
            [arlo_event_start_date format="D j M" wrap='<span class="arlo-item-header">%s</span>']
            <span class="arlo-event-time">[arlo_event_start_date format="D g:i A"] - [arlo_event_end_date format="D g:i A"]</span>
            [arlo_event_session_list_item]
                <div class="arlo_session">
                    <h6>[arlo_event_name]</h6>
                    <div>[arlo_event_start_date format="%a %d %b %H:%M"] - [arlo_event_end_date format="%a %d %b %H:%M"]</div>
                    [arlo_event_location]
                </div>
            [/arlo_event_session_list_item]
            [arlo_event_location wrap="<span class='arlo-event-location'>%s</span>"]
            <div class="arlo-presenters">[arlo_event_presenters wrap="Presented by %s"]</div>
            [arlo_event_offers]
            [arlo_event_registration]
        </li>
        [/arlo_event_list_item]
    [/arlo_event_list]
</ul>

[arlo_event_template_register_interest]
[arlo_suggest_datelocation wrap="<div class='arlo-suggest'>%s</div>"]

<div class="arlo-content-fields">
    [arlo_content_field_item]
        <div class="arlo-content-field">[arlo_content_field_name wrap='<h4>%s</h4>']
        <div class="arlo-content-field-description">[arlo_content_field_text wrap='<p>%s</p>']</div>
    </div>
    [/arlo_content_field_item]
</div>