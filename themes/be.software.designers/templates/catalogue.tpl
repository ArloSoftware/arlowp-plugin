<div class="arlo" id="arlo">
    
    <div class="arlo-category-header-wrapper arlo-banner">
            [arlo_category_title wrap='<h1>%s</h1>']
            [arlo_category_header]
    </div>

    <div class="arlo-catalogue-filters">
        [arlo_categories wrap='<div class="arlo-categories">%s</div>']

        [arlo_event_template_filters filters="location"]
        [arlo_template_region_selector]
    </div>

    <ul class="arlo-list catalogue">
        [arlo_event_template_list_item group="category" limit="10"]

            [arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider"><h2>%s</h2></li>']

            <li class="arlo-cf arlo-catalogue-event">
                [arlo_event_template_permalink wrap='
                <h3><a href="%s" class="arlo-template-name">
                    '][arlo_event_template_name]
                </a></h3>
                <div class="arlo-template-details">
                    [arlo_event_template_summary wrap="<div class='arlo-summary'>%s</div>"]
                  [arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration">%s</div>']
                    [arlo_event_price wrap="<div class='arlo-offers arlo-color2'>%s</div>" showfrom="false"]
                    [arlo_event_template_tags layout="class" wrap='<div class="arlo-delivery arlo-color4 %s">Delivery<div class="arlo-delivery-icons"></div></div>']

                    <div class="arlo-next-running">
                        [arlo_event_next_running text="{%location%} {%date%}"]
                    </div>
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
