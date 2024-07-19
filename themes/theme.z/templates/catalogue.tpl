<div id="arloapp" class="arlo-desktop">
  <div class="arlo-wrapper arlo-catalog arlo-container" role="main">
    <div class="arlo-catalog-region">
        [arlo_category_title wrap="<h1 role='heading'>%s</h1>"]
        [arlo_template_region_selector]
    </div>
  
  
    <div class="arlo-catalog-header">
      [arlo_category_header ]
    </div>
    <div class="arlo-catalog-moreheader" tabindex="0" role="button">
      <span>See more</span> <i aria-hidden="true" class="fa-solid fa-plus"></i><i aria-hidden="true" class="fa-solid fa-minus"></i>
    </div>
  
  
    <div class="arlo-filter-mobile ">
        <span>Filters</span>
        <div class="arlo-icon-wrapper nomargin large outline gray arlo-catalog-mobild-filter"><i class="fa-solid fa-sliders"></i><i class="fa-solid fa-xmark"></i></div>
    </div>
  
  
    <div class="arlo-catalog-filters">
        <div class="arlo-catalog-filters-nav" role="navigation" aria-label="Catalogue">
            [arlo_category_breadcrumb item="<span role='button' tabindex='0' data-slug='{slug}'>{label}</span>" divider='<div aria-hidden="true" class="arlo-triangle"></div>']
        </div>
  
        <!-- <div class="arlo-catalog-filters-tags" role="listbox" aria-label="Please select the category">
            [arlo_categories  counts=" (%s)"]
        </div> -->
        [arlo_event_template_filters filters="category,location,delivery,templatetag" hidereset="true" buttonclass="arlo-button" wrap='<div class="arlo-catalog-filters-selects">%s</div>']
    </div>
  
    <div class="arlo-catalog-category-wrppaer">
      [arlo_event_template_list_item 
          group="category" 
          climit="3"
          limit="3"
          group_header="<h2>%s</h2>"
          category_before="<div class='arlo-catalog-category'>"
          category_after="</div>"
          events_before="<div class='arlo-events' role='list'>" 
          events_after="</div><div class='arlo-align-center-row'><button data-slug='%s' class='arlo-button arlo-gray arlo-btn-more-event'>Show more</button></div>"
      ]
          <div class="arlo-events-item" role="listitem">
            <div>
                <div class="arlo-events-item-cover">
                [arlo_event_template_list_image alt_use_event_name="true"]
                [arlo_event_next_running ignore_resiter_link="true" template_link="none" removeyear="false" format_as_html="true" format='<div class="arlo-events-item-cover-date"><p>%b</p><p>%d</p><p>%Y</p></div>']
                </div>
                <div class="arlo-events-item-main">
                    [arlo_event_template_permalink wrap='<h3><a href="%s">'][arlo_event_template_name]</a></h3>
                    [arlo_event_template_summary wrap='<p>%s</p>' digest='160']
                    <hr />
                    [arlo_event_template_tags layout="list" wrapperclass="arlo-events-item-main-tags"]
                    <div class="arlo-events-item-main-info">
                        [arlo_event_next_running ignore_resiter_link="true" template_link="locationlink" wrap='<div aria-label="event location" ><i class="fa-solid fa-location-dot"></i><span>%s</span></div>' text='{%location%}']
                        [arlo_event_next_running ignore_resiter_link="true" template_link="presenterlist" wrap='<div aria-label="event presenters"><i class="fa-solid fa-user"></i><div class="arlo-event-presenters">%s</div></div>' text='{%location%}']
                        [arlo_event_template_advertised_duration wrap='<div aria-label="event time"><i role="gridcell" class="fa-solid fa-clock"></i><span>%s</span></div>']
                        <div aria-label="event price"><i role="gridcell" class="fa-solid fa-tag"></i>[arlo_event_price wrap="<span><strong>%s</strong></span>" showfrom="true"]</div>
                    </div>
                </div>
            </div>
            [arlo_event_next_running template_link="permalink" aftertext=' <i role="presentation" class="fa-solid fa-arrow-right"></i>' text='View upcoming dates']
          </div>
      [/arlo_event_template_list_item]
    </div>
  
    <div class="arlo-align-center-row">
        <button class="arlo-button arlo-gray arlo-btn-more-category">Show more categories</button>
    </div>
  
    
    
    [arlo_no_event_text before='<div class="arlo-not-found">' after='<a href="">Enquire about a course you are interested in.</a></div>']
   
    <div class="arlo-pager">
      [arlo_event_template_list_pagination climit="3" limit="3" wrap='<div class="arlo-pagination">%s</div>']
    </div>
    <div class="arlo-catalog-footer">
      [arlo_category_footer ]
    </div>
    [arlo_powered_by]
  </div>
</div>