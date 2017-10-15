<div class="arlo" id="arlo">
    
[arlo_categories wrap='<div class="arlo-categories">%s</div>']
[arlo_template_region_selector]
[arlo_event_template_filters buttonclass="arlo-button"]
[arlo_category_header wrap='<div class="arlo-category-header">%s</div>']

    <ul class="arlo-list catalogue">
        [arlo_event_template_list_item group="category" limit="10"]
              [arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider"><h2 class="arlo-font2">%s</h2></li>']
              <li class='arlo-cf arlo-catalogue-event arlo-background-color2'>
                <div class="arlo-catalogue-details">
                  <div class="arlo-catalogue-column arlo-event-name">
                  [arlo_event_template_permalink wrap='
                  <a href="%s" class="arlo-template-name">
                      '][arlo_event_template_name]
                  </a>
                  [arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration">%s</div>']
                  </div>

                  <div class="arlo-catalogue-column arlo-template-details">
                    [arlo_event_price wrap="<div class='arlo-offers'>%s</div>" showfrom="false"]
                  </div>

                </div>

                <div class="arlo-catalogue-column arlo-next-running">
                    [arlo_event_next_running]
                </div>
            </li>
            [arlo_event_template_rich_snippet]
        [/arlo_event_template_list_item]
    </ul>

[arlo_category_footer wrap='<div class="arlo-category-footer">%s</div>']
[arlo_no_event_text]
[arlo_event_template_list_pagination limit="10" wrap='<div class="arlo-pagination">%s</div>']
[arlo_powered_by]

</div>
