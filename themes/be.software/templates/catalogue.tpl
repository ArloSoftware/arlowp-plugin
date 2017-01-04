<div class="arlo">
	
    <div class="arlo-category-header-wrapper arlo-full-width arlo-banner-outer">
		<div class="arlo-category-header-inner arlo-banner-inner">
            [arlo_category_title]
            [arlo_category_header]
        </div>
	</div>

	<div class="arlo-catalogue-filters">
        [arlo_categories wrap='<div class="arlo-categories">%s</div>']

        [arlo_event_template_filters filters="location"]
	</div>

	<ul class="arlo-list catalogue">
        [arlo_event_template_list_item group="category" limit="10"]

            [arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider">%s</li>']

            <li class="arlo-cf arlo-catalogue-event">
                [arlo_event_template_permalink wrap='
                <a href="%s" class="arlo-template-name">
                    <h3>'][arlo_event_template_name]</h3>
                </a>
                <div class="arlo-template-details">
                    [arlo_event_template_summary wrap="<div class='arlo-summary'>%s</div>"]
                    [arlo_event_duration wrap="<div class='arlo-duration'><i class='fa fa-clock-o'></i> %s</div>"]
                    [arlo_event_price wrap="<div class='arlo-offers'>%s</div>"]
                    [arlo_event_template_tags layout="class" wrap='<div class="arlo-delivery %s">Delivery<div class="arlo-delivery-icons"></div></div>']

                    <div class="arlo-next-running">
                        [arlo_event_next_running text="{%location%} {%date%}"]
                    </div>
                </div>
            </li>

        [/arlo_event_template_list_item]
	</ul>

    [arlo_no_event_text]

	<div class="arlo-pagination">
        [arlo_event_template_list_pagination limit="10"]
    </div>

    [arlo_category_footer wrap="<div class='arlo-category-footer-wrapper arlo-full-width'><div class='arlo-category-footer'>%s</div></div>"]
</div>