<div class="arlo" id="arlo">
    <div class="arlo-map">
        [arlo_venue_map height="175" width="1000"]
    </div>

    <div class="normal-text">
      <i class="icons8-marker pull-left lh-25"></i>
      <p class="m-l-20">
        [arlo_venue_address layout="string" items="line1" wrap="%s"]
        [arlo_venue_address layout="string" items="line2" wrap="<br>%s"]
        [arlo_venue_address layout="string" items="line3" wrap="<br>%s"]
        [arlo_venue_address layout="string" items="line4" wrap="<br>%s"]
        [arlo_venue_address layout="string" items="suburb,city" wrap="<br>%s"]
        [arlo_venue_address layout="string" items="state" wrap="%s"]
        [arlo_venue_address layout="string" items="post_code" wrap="%s"]
        [arlo_venue_address layout="string" items="country" wrap="<br>%s"]
      </p>
    </div>

    <div class="row row-fix sm-m-t-0 m-t-30 directions-parking">
      <div class="col-xs-12 col-md-6 md-p-l-0 md-p-r-0">
          [arlo_venue_directions label='<h4 class="m-t-0">Directions</h4>']
      </div>
      <div class="col-xs-12 col-md-6 md-p-l-0 md-p-r-0">
          [arlo_venue_parking label='<h4 class="m-t-0">Parking</h4>']
      </div>
    </div>

    [arlo_venue_events_link link_page="schedule" wrap='<a href="%s" class="btn btn-secondary m-t-10 m-r-10" target="_blank">View scheduled</a>']
    [arlo_venue_events_link link_page="upcoming" wrap='<a href="%s" class="btn btn-secondary m-t-10 m-r-10" target="_blank">View upcoming</a>']

    [arlo_venue_rich_snippet]
</div>