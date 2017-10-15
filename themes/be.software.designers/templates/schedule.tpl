<div class="arlo" id="arlo">

<div class="arlo-category-header-wrapper arlo-banner">
        [arlo_category_title wrap='<h1>%s</h1>']
        [arlo_category_header]
</div>

<div class="arlo-schedule-filters">
    [arlo_event_template_filters filters="category,delivery,location"]
    [arlo_template_region_selector]
</div>

<ul class="arlo-list schedule">
    [arlo_event_template_list_item group="category" limit="10"]
        [arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider"><h3>%s</h3></li>']

        <li class='arlo-cf arlo-schedule-event'>
          <div class="arlo-schedule-column arlo-event-name">
            [arlo_event_template_permalink wrap='
            <a href="%s" class="arlo-template-name">
                '][arlo_event_template_name]
            </a>
          </div>
          <div class="arlo-schedule-column arlo-event-duration">
             [arlo_event_template_advertised_duration wrap='<div class="arlo-advertised-duration">%s</div>']
          </div>
          <div class="arlo-schedule-column arlo-price">
             [arlo_event_price wrap="<div class='arlo-offers arlo-color2'><i class='icons8-price-tag-filled'></i> %s</div>"]
          </div>
          <div class="arlo-schedule-column arlo-next-running">
             [arlo_event_next_running limit="3"]
          </div>
        </li>
      [arlo_event_template_rich_snippet]
    [/arlo_event_template_list_item]
</ul>

[arlo_category_footer wrap='<div class="arlo-category-footer">%s</div>']
[arlo_no_event_text]
[arlo_schedule_pagination limit="10" wrap='<div class="arlo-pagination">%s</div>']
[arlo_powered_by]

</div>