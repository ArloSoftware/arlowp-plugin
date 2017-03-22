<div class="arlo" id="arlo">

[arlo_template_region_selector]

    <ul class="arlo-list event-search catalogue">
        [arlo_event_template_list_item group="category" limit="10"]
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
                    [arlo_event_price wrap="<div class='arlo-offers'>%s</div>"]
                  </div>

                </div>

                <div class="arlo-catalogue-column arlo-next-running">
                    [arlo_event_next_running]
                </div>
            </li>
        [/arlo_event_template_list_item]
    </ul>

[arlo_no_event_text]
[arlo_event_template_list_pagination wrap='<div class="arlo-pagination">%s</div>']

</div>