<div class="arlo" id="arlo">
    [arlo_template_region_selector]
    [arlo_onlineactivites_filters]

    <ul class="arlo-online-activities arlo-list">
        [arlo_onlineactivites_list_item limit="10" group="category"]
        [arlo_group_divider wrap='<li class="arlo-cf arlo-online-activity arlo-group-divider"><h2>%s</h2></li>']
        <li class="arlo-cf arlo-online-activity">
            <div class="arlo-icon arlo-color5">
                <i class="icons8-tv-show"></i>
            </div>

            <div class="arlo-content">
                <h3>[arlo_event_template_permalink wrap='<a href="%s">'][arlo_oa_name]</a></h3>

                [arlo_event_template_summary wrap='<p class="arlo-summary">%s</p>']

                [arlo_oa_offers]

                <div class="arlo-buttons">
                    [arlo_event_template_permalink wrap='<a href="%s" class="arlo-button arlo-background-color5">']More Information</a>
                    [arlo_oa_registration]
                </div>
            </div>

        </li>
        [arlo_oa_rich_snippet]
        [/arlo_onlineactivites_list_item]
    </ul>

    [arlo_onlineactivites_list_pagination limit="10" wrap='<div class="arlo-pagination">%s</div>']
    
</div>