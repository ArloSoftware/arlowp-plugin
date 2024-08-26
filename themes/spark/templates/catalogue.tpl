<div class="arlo" id="arlo">

    <div class="arlo-category-header-wrapper arlo-banner">
            [arlo_category_header]
    </div>

    <div class="arlo-catalogue-filters">
        [arlo_template_region_selector]
        [arlo_event_template_filters filters="location"]
        [arlo_categories wrap='<div class="arlo-categories">%s</div>']
    </div>

    <ul class="arlo-list catalogue">
        [arlo_event_template_list_item group="category" limit="10"]
            [arlo_group_divider wrap='<div class="arlo-cf arlo-group-divider"><h2>%s</h2></div>']

            <li class="arlo-cf arlo-catalogue-event">
                <div class="arlo-left">
                    [arlo_event_template_permalink wrap='
                    <h3 class="arlo-template-name"><a href="%s">
                        '][arlo_event_template_name]
                    </a></h3>

                    <div class="arlo-template-details">
                        [arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration"><i class="icons8-clock"></i><span>%s</span></div>']

                        [arlo_event_template_summary wrap="<div class='arlo-summary'>%s</div>"]
                        [arlo_event_price wrap="<div class='arlo-offers'><i class='icons8-price-tag'></i><span>%s</span></div>" showfrom="true"]
                    </div>
                </div>

                <div class="arlo-next-running arlo-right">

                    [arlo_event_next_running label="<p>Next running</p>" buttonclass="button arlo-button" text="{%location%} {%date%}"]
                </div>
            </li>
            [arlo_event_template_rich_snippet]

        [/arlo_event_template_list_item]
    </ul>

    [arlo_no_event_text]

    <div class="arlo-pagination">
        [arlo_event_template_list_pagination limit="10"]
    </div>

    [arlo_category_footer wrap="<div class='arlo-category-footer-wrapper'><div class='arlo-category-footer'>%s</div></div>"]
    [arlo_powered_by]
    
</div>
