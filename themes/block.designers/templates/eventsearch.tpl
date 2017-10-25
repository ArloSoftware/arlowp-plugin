<div class="arlo" id="arlo">

[arlo_template_region_selector]

[arlo_search_field placeholder="Search events" buttonclass="arlo-button" showbutton="true"]

    <ul class="arlo-list event-search">
        [arlo_event_template_list_item limit="10"]
              <li class='arlo-cf arlo-catalogue-event'>
                  [arlo_event_template_permalink wrap='
                  <a href="%s" class="arlo-template-name">
                      ']<h4>[arlo_event_template_name]</h4>
                  </a>
                  [arlo_event_template_summary wrap='<div class="arlo-summary">%s</div>']

                  [arlo_event_price wrap="<div class='arlo-offers'>%s</div>"]

                  [arlo_event_next_running wrap='<div class="arlo-next-running">%s</div>' limit="3"]
            </li>
            [arlo_event_template_rich_snippet]
        [/arlo_event_template_list_item]
    </ul>

[arlo_no_event_text]
[arlo_event_template_list_pagination limit="10" wrap='<div class="arlo-pagination">%s</div>']
[arlo_powered_by]
</div>
