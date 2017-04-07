<div class="arlo" id="arlo">

[arlo_template_region_selector]

    <ul class="arlo-list event-search catalogue">
        [arlo_event_template_list_item group="category" limit="10"]
              <li class='arlo-cf arlo-catalogue-event arlo-background-color2'>
                  [arlo_event_template_permalink wrap='
                  <a href="%s" class="arlo-template-name">
                      ']<h4>[arlo_event_template_name]</h4>
                  </a>
                  [arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration arlo-background-color1">%s</div>']
                  [arlo_event_template_summary wrap='<div class="arlo-summary">%s</div>']

                  [arlo_event_price wrap="<div class='arlo-offers'>%s</div>"]

                  [arlo_event_next_running wrap='<div class="arlo-next-running">%s</div>']
            </li>
        [/arlo_event_template_list_item]
    </ul>

[arlo_no_event_text]
[arlo_event_template_list_pagination wrap='<div class="arlo-pagination">%s</div>']

</div>