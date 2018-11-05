<div class="arlo" id="arlo">
[arlo_template_region_selector wrap="<div class='arlo-region-selector'>%s</div>"]

[arlo_category_title wrap="<h1>%s</h1>"]
[arlo_category_header]

[arlo_categories title=" " wrap='<div class="arlo-categories m-t-10 collapse" id="arlo-categories">%s</div>']

  <button data-toggle="collapse" data-target="#arlo-categories" class="btn form-control full-width m-b-20 filter-toggle collapsed">
      <div class="display-filters">
        Display filters
      </div>

      <div class="hide-filters">
        Hide filters
      </div>
    </button>



<div class="clearfix row-fix row events">
[arlo_event_template_list_item group="category" limit="20"]
    [arlo_group_divider wrap="<h2 class='m-b-30 clearfix sm-p-r-0 sm-p-l-0 p-r-15 p-l-15'>%s</h2>"]

        <div class="col-lg-4 col-md-6 col-xs-12 m-b-30 sm-m-b-20 md-no-padding catalogue-item has-thumbnail">
          <div class="drop-shadow bg-white relative no-overflow">
            <div class="image-thumbnail">
              [arlo_event_template_list_image]
            </div>
            <div class="template-details relative">
              <div class="align-top xs-padding-0 padding-20">
                <h4 class='m-t-0 m-l-0 m-r-0 m-b-10 truncate-2 sm-m-b-5 name'>[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a></h4>
                <p class="normal-text muted truncate-4 hidden-xs summary">[arlo_event_template_summary]</p>
              </div>
              <div class="align-bottom">

                [arlo_event_template_advertised_duration wrap='<p class="truncate-1 sm-m-b-0 duration"><i class="icons8-clock hidden-xs pull-left"></i> <span class="block m-l-20 xs-m-l-0">%s</span></p>']

                <p class="truncate-1 m-b-0 price"><i class="icons8-price-tag hidden-xs pull-left"></i> <span class="block m-l-20 xs-m-l-0">[arlo_event_price]</span></p>
              </div>
              <a href="#" class="btn-expand mobile hidden">
                <i class="fa fa-angle-down" aria-hidden="true"></i>
              </a>
            </div>

            <div class="bg-master-lighter relative padding-20 block large-text hidden-xs">
                [arlo_event_next_running text='View upcoming dates ({%count%})']
            </div>


            <div class="mobile-expanded-visible">
              <div class="sm-padding-15">
                <p class="normal-text">[arlo_event_template_summary]</p>
                [arlo_event_template_permalink wrap='<a class="btn btn-secondary-alt" href="%s">More Information</a>']
              </div>
            </div>
          </div>
        </div>

    [arlo_event_template_rich_snippet]
[/arlo_event_template_list_item]
</div>

[arlo_category_footer wrap='<div class="m-t-20 m-b-20">%s</div>']
[arlo_no_event_text]
[arlo_event_template_list_pagination limit="20" wrap='<div class="arlo-pagination">%s</div>']
[arlo_powered_by]

</div>