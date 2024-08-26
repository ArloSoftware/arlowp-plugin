<div class="arlo" id="arlo">

    [arlo_template_search_region_selector]

    [arlo_search_field placeholder="Search events" buttonclass="arlo-button" showbutton="true" wrap='<div class="arlo-background-color3">%s</div>']

    <ul class="arlo-list event-search-list">
        [arlo_event_template_list_item limit="10"]

            [arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider"><h3>%s</h3></li>']

            <li class="arlo-cf">
                [arlo_event_template_permalink wrap='
                <h4><a href="%s" class="arlo-template-name">
                    '][arlo_event_template_name]
                </a></h4>
                <div class="arlo-template-details">
                    [arlo_event_template_summary wrap="<div class='arlo-summary'>%s</div>"]
                    [arlo_event_price wrap="<div class='arlo-offers arlo-color2'>%s</div>"]

                    <div class="arlo-next-running">
                        [arlo_event_next_running text="{%location%} {%date%}" limit="3"]
                    </div>
                </div>
            </li>
            [arlo_event_template_rich_snippet]

        [/arlo_event_template_list_item]
    </ul>
    
    <div class="arlo-pagination">
        [arlo_event_template_list_pagination limit="10"]
    </div>
    [arlo_no_event_text]
    [arlo_powered_by]
</div>
