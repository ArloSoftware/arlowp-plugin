<div class="arlo" id="arlo">

    [arlo_template_search_region_selector wrap='<div class="arlo-filter-region-search">%s</div>']

    [arlo_search_field placeholder="Search events" buttonclass="arlo-button" showbutton="true" wrap='<div class="arlo-cf">%s</div>']

    <ul class="arlo-list catalogue event-search">
        [arlo_event_template_list_item limit="10"]

            [arlo_group_divider wrap='<div class="arlo-cf arlo-group-divider"><h2>%s</h2></div>']

            <li class="arlo-cf arlo-catalogue-event">
                [arlo_event_template_permalink wrap='
                <h3 class="arlo-template-name"><a href="%s">
                    '][arlo_event_template_name]
                </a></h3>

                <div class="arlo-template-details">
                    [arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration"><i class="icons8-clock"></i><span>%s</span></div>']

                    [arlo_event_template_summary wrap="<div class='arlo-summary'>%s</div>"]
                    [arlo_event_price wrap="<div class='arlo-offers'><i class='icons8-price-tag'></i><span>%s</span></div>" showfrom="true"]
                </div>

                <div class="arlo-next-running">
                    [arlo_event_next_running label="<p>Next running</p>" buttonclass="button arlo-button" text="{%location%} {%date%}" limit="3"]
                </div>
            </li>
            [arlo_event_template_rich_snippet]
        [/arlo_event_template_list_item]
    </ul>

    [arlo_no_event_text]

    <div class="arlo-pagination">
        [arlo_event_template_list_pagination limit="10"]
    </div>
    [arlo_powered_by]
</div>
