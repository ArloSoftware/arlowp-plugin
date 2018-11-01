<div class="arlo" id="arlo">
  [arlo_template_region_selector wrap="<div class='arlo-region-selector'>%s</div>"]
  [arlo_category_title wrap="<h1>%s</h1>"]
  [arlo_category_header]

    <div class="arlo-schedule-filters collapse" id="filters">
        [arlo_schedule_filters filters="location,category,delivery"]
    </div>

    <button data-toggle="collapse" data-target="#filters" class="btn form-control full-width m-b-20 filter-toggle collapsed">
      <div class="display-filters">
        Display filters
      </div>

      <div class="hide-filters">
        Hide filters
      </div>
    </button>

    [arlo_event_template_list_item group="category" limit="20"]
        [arlo_group_divider wrap='<h2 class="sm-m-b-15 m-b-30 m-t-30 clearfix">%s</h2>']          

          <div class="clearfix row bg-white padding-20 p-l-25 p-r-25 schedule-item m-b-10 sm-p-l-10 sm-p-r-10 sm-p-t-10 sm-p-b-10">
            <div class="col-md-6 col-xs-12 p-l-0 p-r-0">
              <p class='truncate-2 name m-t-0 md-p-r-40'>[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a></p>
              <a href="#" class="btn-expand mobile">
                <i class="fa fa-angle-down" aria-hidden="true"></i>
              </a>
              <div class="mobile-expanded-visible xl-p-b-35">
                <p class="normal-text truncate-3 summary m-b-0 md-m-b-15">[arlo_event_template_summary]</p>
                <div class="align-bottom template-details">
                  [arlo_event_template_advertised_duration wrap='<p class="truncate-1 inline md-block duration m-r-20 m-b-0 md-m-b-10"><i class="icons8-clock pull-left"></i> <span class="block m-l-20">%s</span></p>']
                </div>
              </div>
            </div>
            <div class="col-md-6 col-xs-12 md-p-l-0 p-l-30 p-r-0">

              <div class="scheduled-dates">
                [arlo_event_next_running text="<div class='normal-text truncate-1'>{%date%}</div><div class='location truncate-1'>{%location%}</div>" limit="12" format="period"]
              </div>
            </div>
          </div>
      [arlo_event_template_rich_snippet]
    [/arlo_event_template_list_item]


[arlo_category_footer]
[arlo_no_event_text]
[arlo_schedule_pagination limit="20"]
[arlo_powered_by]
</div>