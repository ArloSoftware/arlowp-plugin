<div class="arlo arlo-boxed" id="arlo">
    [arlo_template_region_selector wrap="<div class='arlo-region-selector'>%s</div>"]
    <div class="arlo-oa-filters collapse" id="filters">[arlo_onlineactivites_filters]</div>

    <button data-toggle="collapse" data-target="#filters" class="btn form-control full-width m-b-20 filter-toggle collapsed">
      <div class="display-filters">
        Display filters
      </div>

      <div class="hide-filters">
        Hide filters
      </div>
    </button>


    <ul class="arlo-online-activities arlo-list clearfix">
        [arlo_onlineactivites_list_item limit="10" group="category"]
        [arlo_group_divider wrap='<li class="arlo-cf arlo-group-divider"><h2 class="sm-m-b-15 m-b-30 m-t-30 clearfix">%s</h2></li>']
        <li class="clearfix row bg-white padding-20 p-l-25 p-r-25 arlo-online-activity m-b-10 sm-p-l-10 sm-p-r-10 sm-p-t-10 sm-p-b-10">
            <h4 class="name m-t-0">[arlo_event_template_permalink wrap='<a href="%s">'][arlo_oa_name]</a></h4>
                
            [arlo_event_template_summary wrap='<p class="normal-text muted truncate-4 summary m-b-20">%s</p>']

            [arlo_oa_reference_term wrap='<div class="md-block duration m-r-20 m-b-15 xs-m-b-5"><i class="icons8-tv-show pull-left"></i> <div class="m-l-25">%s</div></div>']

            [arlo_oa_offers wrap='<div class="md-block offers m-r-20 m-b-15 xs-m-b-5"><i class="icons8-price-tag pull-left"></i> <div class="m-l-25">%s</div></div>']

            [arlo_oa_registration]
        </li>
        [arlo_oa_rich_snippet]
        [/arlo_onlineactivites_list_item]
    </ul>

    [arlo_onlineactivites_list_pagination limit="10" wrap='<div class="arlo-pagination">%s</div>']
    
</div>