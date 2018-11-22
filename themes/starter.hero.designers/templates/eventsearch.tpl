<div class="arlo" id="arlo">
    [arlo_template_region_selector wrap="<div class='arlo-region-selector'>%s</div>"]

    [arlo_search_field placeholder="Search events" buttonclass="btn-primary" showbutton="true" buttontext='Search']

    [arlo_event_template_list_item limit="10"]
          <div class="clearfix row search-item m-b-15 p-b-20 b-b b-grey">

            <h4 class='truncate-2 name m-t-0 md-p-r-40 m-b-0'>
            [arlo_event_template_permalink wrap='<a href="%s" class="text-primary-dark">'][arlo_event_template_name]</a>
            </h4>

            <p class="normal-text truncate-3 summary m-b-10">[arlo_event_template_summary]</p>

            <div class="search-scheduled-dates">
                [arlo_event_next_running limit="10"]
            </div>

          </div>
        [arlo_event_template_rich_snippet]
    [/arlo_event_template_list_item]

[arlo_no_event_text]
[arlo_event_template_list_pagination limit="10" wrap='<div class="arlo-pagination">%s</div>']
[arlo_powered_by]
</div>