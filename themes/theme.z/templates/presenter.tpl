<div id="arloapp" class="arlo-desktop">
<div class="arlo-wrapper arlo-presenter">
  <div class="arlo-presenter-info">
      <div class="arlo-presenter-info-basic">
          <div class="arlo-presenter-info-basic-avatar">
              [arlo_presenter_profile_avatar placeholder="themes/theme.z/images/noimage.jpg"]
          </div>
          <div>
              <h1>[arlo_presenter_name]</h1>
              <div class="arlo-presenter-info-basic-social">
                  [arlo_presenter_social_link linkclass="arlo-icon-wrapper outline nomargin" network='Twitter' linktext='<i class="fa-brands fa-twitter"></i>']
                  [arlo_presenter_social_link linkclass="arlo-icon-wrapper outline nomargin" network='linkedin' linktext='<i class="fa-brands fa-linkedin"></i>']
                  [arlo_presenter_social_link linkclass="arlo-icon-wrapper outline nomargin" network='Facebook' linktext='<i class="fa-brands fa-facebook"></i>']
                  [arlo_presenter_link wrap='<a class="arlo-icon-wrapper outline nomargin" aria-label="link" href="%s"><i class="fa-solid fa-link"></i></a>']
              </div>
          </div>
      </div>
      <div class="arlo-presenter-info-description">
            [arlo_presenter_profile wrap='<div class="arlo-presenter-info-description-content">%s</div>']
            [arlo_presenter_interests wrap='<div class="arlo-presenter-info-description-section">Interests</div><div class="arlo-presenter-info-description-content">%s</div>']
            [arlo_presenter_qualifications wrap='<div class="arlo-presenter-info-description-section">Qualifications</div><div class="arlo-presenter-info-description-content">%s</div>']
      </div>
  </div>
  
    [arlo_presenter_events_list_advanced wrap='<hr class="arlo-presenter-divider" /><div class="arlo-presenter-events"><h2>Events by the presenter</h2><div class="arlo-events "> <div class="swiper"><div class="swiper-wrapper">%s</div></div></div>']
    <div class="swiper-slide">
    <div class="arlo-events-item">
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
    </div>
    [/arlo_presenter_events_list_advanced]
</div>
</div>