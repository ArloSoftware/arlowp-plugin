<div class="arlo" id="arlo">

    <div class="arlo-category-header-wrapper arlo-banner">
            [arlo_category_header]
    </div>

    [arlo_template_region_selector]

    <div class="arlo-catalogue-filters">
        [arlo_event_template_filters filters="category,delivery,location"]
    </div>

    <ul class="arlo-list schedule">
        [arlo_event_template_list_item group="category" limit="10"]
            [arlo_group_divider wrap='<div class="arlo-cf arlo-group-divider"><h2>%s</h2></div>']

            <li class="arlo-cf arlo-schedule-event">
                <div class="arlo-schedule-column arlo-event-name">
                    [arlo_event_template_permalink wrap='
                    <h5 class="arlo-template-name"><a href="%s">
                        '][arlo_event_template_name]
                    </a></h5>

                    [arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration arlo-color7"><i class="icons8-clock"></i><span>%s</span></div>']
                </div>

                <div class="arlo-schedule-column arlo-price">
                    [arlo_event_price wrap="<div class='arlo-offers arlo-color7'><i class='icons8-price-tag'></i><span>%s</span></div>" showfrom="true"]
                </div>

                <div class="arlo-schedule-column arlo-next-running">
                    [arlo_event_next_running limit="3"]
                </div>
            </li>
          [arlo_event_template_rich_snippet]
        [/arlo_event_template_list_item]
    </ul>

    [arlo_no_event_text]

    <div class="arlo-pagination">
        [arlo_schedule_pagination limit="10"]
    </div>

    [arlo_category_footer wrap="<div class='arlo-category-footer-wrapper'><div class='arlo-category-footer'>%s</div></div>"]
    [arlo_powered_by]
    
</div>
