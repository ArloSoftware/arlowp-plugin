<div class="arlo" id="arlo">
    [arlo_template_region_selector] [arlo_onlineactivites_filters]

    <ul class="arlo-online-activities arlo-list">
        [arlo_onlineactivites_list_item limit="10" group="category"] [arlo_group_divider wrap='
        <div class="arlo-cf arlo-online-activity arlo-group-divider arlo-font2">
            <h2>%s</h2>
        </div>']
        <li class="arlo-cf arlo-online-activity">
            <div class="arlo-left">
                <h3 class="arlo-template-name">
                    [arlo_event_template_permalink wrap='<a href="%s">'][arlo_oa_name]</a>
                </h3>
                [arlo_event_template_summary wrap="<p>%s</p>"]
                [arlo_oa_offers]
            </div>
            <div class="arlo-right">
                [arlo_oa_reference_term wrap='<div class="arlo-reference-term">%s</div>']
                [arlo_oa_registration]
            </div>
        </li>
        [arlo_oa_rich_snippet]
        [/arlo_onlineactivites_list_item]
    </ul>

    [arlo_onlineactivites_list_pagination limit="10" wrap='
    <div class="arlo-pagination">%s</div>']
    
</div>
