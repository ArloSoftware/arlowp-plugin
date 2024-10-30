<div id="arloapp" class="arlo-desktop arlo-event-page">
  <div class="arlo-wrapper full arlo-event">
    
    [arlo_condition_return 
      param=''
      shortcode_value='arlo_event_template_hero_image'
      cond="equal" 
      true='<div class="arlo-event-basic noimage">'
      false='<div class="arlo-event-basic">']
    [/arlo_condition_return]
      [arlo_event_template_hero_image alt_use_event_name="true" wrap='<div class="arlo-event-basic-cover">%s</div>']
      <div class="arlo-event-basic-info">
        <div>
          [arlo_event_template_tags layout="list" wrapperclass="arlo-tags outline"]
          <h1>[arlo_event_template_name]</h1>
          [arlo_event_template_summary wrap='<p>%s</p>']
          <div class="arlo-event-basic-info-detail">
            [arlo_condition_return 
              param=''
              shortcode_value='arlo_no_event_in_region'
              cond="equal" 
              return_content="true"]
              <div class="arlo-event-basic-info-detail-left">
                [arlo_event_template_advertised_duration wrap='<div><i class="fa-solid fa-clock"></i>%s</div>']
                <div><i class="fa-solid fa-tag"></i>[arlo_event_template_advertised_price]
                </div>
              </div>
              <div class="arlo-event-basic-info-detail-right">[arlo_event_template_advertised_presenters format="link" wrap='<span><i class="fa-solid fa-user"></i></span><p>%s</p>']</div>
            [/arlo_condition_return]
          </div>
          [arlo_condition_return 
            param=''
            shortcode_value='arlo_no_event_in_region'
            cond="equal" 
            return_content="true"]
            <button href class="arlo-button outline-reverse arlo-button-book-now">Book now</button>
          [/arlo_condition_return]
        </div>
      </div>
    </div>
  </div>

  <div class="arlo-wrapper arlo-event">
    <div class="arlo-main arlo-event-filter">
      [arlo_template_region_selector]
    </div>
    [arlo_no_event_in_region]
    <div class="arlo-event-regionnotavaliable">
      <p class="arlo-event-regionnotavaliable-title">Oops!</p>
      <p class="arlo-event-regionnotavaliable-desc">This course is not available in the selected region.</p>
      <p class="arlo-event-regionnotavaliable-message">Please select a different region from the region selector above.</p>
    </div>
    [/arlo_no_event_in_region]
    [arlo_content_field_item]
      <div class="arlo-event-section">
        [arlo_content_field_name wrap='<div class="arlo-event-section-title"><p role="heading">%s</p></div>']
        [arlo_content_field_text wrap='<div class="arlo-event-section-content"><hr /><p>%s</p></div>']
      </div>
    [/arlo_content_field_item]

    [arlo_condition_return 
      param=''
      shortcode_value='arlo_no_event_in_region'
      cond="equal" 
      return_content="true"]
    
    <hr />
    <div class="arlo-event-list">
      <div class="arlo-filter-mobile">
        <span>Filters</span>
        <div class="arlo-icon-wrapper nomargin large outline gray arlo-event-filter-icon">
          <i class="fa-solid fa-sliders"></i><i class="fa-solid fa-xmark"></i>
        </div>
      </div>
      <div class="arlo-event-list-filter">
        <div>
          [arlo_event_filters filters="location"]
        </div>
        [arlo_timezones]
      </div>
      
      <div class="arlo-event-list-items" role="list">
        [arlo_event_list]
          [arlo_event_list_item show="4" within_ul="false"]
          <div class="arlo-event-list-items-item" role="listitem" style="display: none;">
            <div class="arlo-event-list-items-item-bg">
              <div class="arlo-event-list-items-item-wrapper">
                <div class="arlo-event-list-items-item-time">
                  <div class="arlo-event-list-items-item-time-calendar">
                    <p>[arlo_event_start_date format="%b"]</p>
                    <p>[arlo_event_start_date format="%d"]</p>
                    <p>[arlo_event_start_date format="%Y"]</p>
                  </div>
                  <div class="arlo-event-list-items-item-time-detail">
                    [arlo_event_duration], [arlo_event_start_date format="%I:%M %p"] - [arlo_event_end_date format="%I:%M %p"]
                    <div class="arlo-event-list-items-item-time-detail-discount">Discount</div>
                  </div>
                </div>
                <div class="arlo-event-list-items-item-info">
                  [arlo_condition_return_level1 param='Online' shortcode_value='arlo_event_location' cond="equal" return_content="true"]
                    [arlo_event_location wrap='<div aria-label="event location" class="arlo-mobile-only arlo-first" ><i class="fa-solid fa-desktop"></i><span>%s</span></div>'][/arlo_event_location]
                  [/arlo_condition_return_level1]
                  [arlo_condition_return_level1  param='Online' shortcode_value='arlo_event_location' cond="nequal" return_content="true"]
                    [arlo_event_location wrap='<div aria-label="event location" class="arlo-mobile-only arlo-first" ><i class="fa-solid fa-location-dot"></i><span>%s</span></div>'][/arlo_event_location]
                  [/arlo_condition_return_level1]

                  [arlo_event_offers wrap='<div aria-label="event offers"  class="arlo-event-offer-wrapper arlo-mobile-only arlo-mobile-offer"><i class="fa-solid fa-tag"></i><div>%s</div></div>']

                  [arlo_event_session_list_item layout="popup" wrap='<div aria-label="event sessions arlo-first" ><i class="fa-solid fa-clock"></i><span>%s</span></div>']
                    <div class="session-row">
                      <div class="session-name">
                        <strong class="m-b-5 block">{%index%}. [arlo_event_name]</strong>
                      </div>
                      <div class="session-time">
                        [arlo_event_start_date format="l" wrap='<div><i class="fa-solid fa-calendar"></i>%s'][arlo_event_start_date format=", %d %b %Y" wrap='%s</div>']
                        [arlo_event_start_date format="%H:%M" wrap='<div><i class="fa-solid fa-clock"></i>%s'][arlo_event_end_date format="%H:%M" wrap='- %s</div>']
                        [arlo_event_location wrap='<div><i class="fa-solid fa-location-dot"></i>%s</div>']
                        [arlo_event_presenters wrap='<div><i class="fa-solid fa-user"></i>%s</a></div>']
                      </div>
                    </div>
                  [/arlo_event_session_list_item]

                  [arlo_condition_return_level1 param='Online' shortcode_value='arlo_event_location' cond="equal" return_content="true"]
                    [arlo_event_location wrap='<div aria-label="event location" class="arlo-non-mobile-only" ><i class="fa-solid fa-desktop"></i><span>%s</span></div>'][/arlo_event_location]
                  [/arlo_condition_return_level1]
                  [arlo_condition_return_level1  param='Online' shortcode_value='arlo_event_location' cond="nequal" return_content="true"]
                    [arlo_event_location wrap='<div aria-label="event location" class="arlo-non-mobile-only" ><i class="fa-solid fa-location-dot"></i><span>%s</span></div>'][/arlo_event_location]
                  [/arlo_condition_return_level1]

                  [arlo_event_presenters wrap='<div aria-label="event presenters"><i class="fa-solid fa-user"></i><div class="arlo-event-presenters">%s</div></div>']
                  [arlo_event_notice wrap='<div aria-label="event special course note" class="arlo-non-mobile-only" ><i class="fa-solid fa-circle-info"></i><span>%s</span></div>']
                  [arlo_event_offers wrap='<div aria-label="event offers"  class="arlo-event-offer-wrapper arlo-non-mobile-only"><i class="fa-solid fa-tag"></i><div>%s</div></div>']
                </div>
              </div>
              <div class="arlo-event-list-items-item-footer">
                [arlo_event_registration class="arlo-button"]
                <div class="arlo-icon-wrapper nomargin large outline gray arlo-event-more-icon">
                  <i class="fa-solid fa-chevron-down"></i><i class="fa-solid fa-chevron-up"></i>
                </div>
              </div>
            </div>
          </div>
          [/arlo_event_list_item]
        [/arlo_event_list]
      </div>
    </div>
    <div class="arlo-align-center-row">
      <button class="arlo-button arlo-event-more arlo-gray">Show more</button>
    </div>
    [arlo_event_template_register_interest]
    <div class="arlo-event-links">
      [arlo_suggest_private_datelocation wrap="<div>%s</div>"]
      [arlo_suggest_datelocation wrap="<div>%s</div>"]
    </div>
    [arlo_oa_registration]
    [/arlo_condition_return]
    <div class="arlo-event-online">
      [arlo_oa_list_item]
      <div class="arlo-event-online-item">
        <div class="arlo-event-online-item-title">
          Online <br />Activity
        </div>
        <div class="arlo-event-online-item-detail">
          <div>
            <p>Complete online at your own pace (Self-paced)</p>
            <div class="arlo-event-online-item-detail-offer"><i class="fa fa-tag"></i>[arlo_oa_offers]</div>
          </div>
        </div>
        [arlo_oa_registration class="arlo-button"]
      </div>
      [/arlo_oa_list_item]
    </div>
    
    [arlo_powered_by]
  </div>
</div>
