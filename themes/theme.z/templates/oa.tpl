<div id="arloapp" class="arlo-desktop">
<div class="arlo-wrapper">
  <div class="arlo-activities-filter">
      [arlo_onlineactivites_filters]
  </div>
  <div class="arlo-activities" role="list"> 
      [arlo_onlineactivites_list_item group="none" limit="6" noevent_before='<div class="arlo-not-found">' noevent_after='<a href="">Enquire about a course you are interested in.</a></div>']
      <div class="arlo-activities-item" role="listitem">
          <div>
              <p class="arlo-activities-item-title">[arlo_event_template_permalink wrap='<a href="%s">'][arlo_oa_name]</a></p>
              <p class="arlo-activities-item-desc">[arlo_event_template_summary digest="160"]</p>
              <div class="arlo-activities-item-note">
                  <i class="fa-solid fa-tag"></i>
                  <span>[arlo_oa_offers]</span>
              </div>
              [arlo_event_template_tags layout="list" wrapperclass="arlo-events-item-main-tags"]
          </div>
          <div>
              <hr />
              <div class="arlo-activities-item-btns" href="">
                  [arlo_event_template_permalink wrap='<a href="%s" class="arlo-button arlo-gray">']Learn more</a>
                  [arlo_oa_registration class="arlo-button"]
              </div>
          </div>
      </div>
      [/arlo_onlineactivites_list_item]
  </div>

  [arlo_onlineactivites_list_pagination limit="6" wrap='<div class="arlo-pager">%s</div>']
</div>
<div class="arlo-align-center-row arlo-mobile-padding">
    <button class="arlo-button arlo-gray arlo-more-oa">Show more</button>
</div>

<div class="arlo-wrapper">
[arlo_powered_by]
</div>
</div>