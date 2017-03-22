<div class="arlo" id="arlo">

    [arlo_template_search_region_selector]

    <ul class="arlo-list event-search-list">
        [arlo_event_template_list_item]

            [arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider"><h2>%s</h2></li>']

            <li class="arlo-cf arlo-catalogue-event">
                [arlo_event_template_permalink wrap='
                <h2><a href="%s" class="arlo-template-name">
                    '][arlo_event_template_name]
                </a></h2>
                <div class="arlo-template-details">
                    [arlo_event_template_summary wrap="<div class='arlo-summary'>%s</div>"]
                    [arlo_event_duration wrap="<div class='arlo-duration'><i class='fa fa-clock-o'></i> %s</div>"]
                    [arlo_event_price wrap="<div class='arlo-offers arlo-color2'>%s</div>"]

                    <div class="arlo-next-running">
                        [arlo_event_next_running text="{%location%} {%date%}"]
                    </div>
                </div>
            </li>

        [/arlo_event_template_list_item]
    </ul>
    
    <div class="arlo-pagination">
       [arlo_event_template_list_pagination]
    </div>
    [arlo_no_event_text]

</div>