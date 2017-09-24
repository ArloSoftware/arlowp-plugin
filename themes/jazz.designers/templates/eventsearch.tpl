<div class="arlo" id="arlo">

[arlo_template_region_selector]

[arlo_search_field placeholder="Search events" buttonclass="arlo-button" showbutton="true" wrap='<div class="arlo-background-color2">%s</div>']

    <ul class="arlo-list event-search">
        [arlo_event_template_list_item limit="10"]
              <li class='arlo-cf'>
                <div class=" arlo-event-name">
                [arlo_event_template_permalink wrap='
                <a href="%s" class="arlo-template-name">
                    ']<h5>[arlo_event_template_name]</h5>
                </a>
                </div>

                [arlo_event_template_summary]

                <div class="arlo-template-details">
                  [arlo_event_price wrap="<div class='arlo-offers'>%s</div>"]
                </div>

                <div class="arlo-next-running">
                    [arlo_event_next_running text="{%location%} {%date%}" limit="3"]
                </div>
            </li>
            [arlo_event_template_rich_snippet]
        [/arlo_event_template_list_item]
    </ul>

[arlo_no_event_text]
[arlo_event_template_list_pagination limit="10" wrap='<div class="arlo-pagination">%s</div>']
[arlo_powered_by]
</div>
