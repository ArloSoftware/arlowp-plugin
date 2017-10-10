<div class="arlo" id="arlo">

    <ul class="arlo-list venues">
        [arlo_venue_list_item]
            <li class="arlo-cf">
                <h2>[arlo_venue_permalink wrap="<a href='%s'>"][arlo_venue_name]</a></h2>
                [arlo_venue_map height="120" width="640" wrap='<div class="arlo-map">%s</div>']

                [arlo_venue_permalink wrap="<div class='arlo-venue-info-link'><a href='%s'>View all venue information</a></div>"]
                [arlo_venue_address layout="string" items="line1" wrap="%s"]
                [arlo_venue_address layout="string" items="line2" wrap="<br>%s"]
                [arlo_venue_address layout="string" items="line3" wrap="<br>%s"]
                [arlo_venue_address layout="string" items="line4" wrap="<br>%s"]
                [arlo_venue_address layout="string" items="suburb,city" wrap="<br>%s"]
                [arlo_venue_address layout="string" items="state" wrap="%s"]
                [arlo_venue_address layout="string" items="post_code" wrap="%s"]
                [arlo_venue_address layout="string" items="country" wrap="<br>%s"]
                [arlo_venue_parking wrap='<div class="arlo-venue-parking arlo-venue-content"><h4>Parking</h4>%s</div>']
                [arlo_venue_directions wrap='<div class="arlo-venue-directions arlo-venue-content"><h4>Directions</h4>%s</div>']

            </li>
            [arlo_venue_rich_snippet]
        [/arlo_venue_list_item]
    </ul>

    <div class="arlo-pagination">
        [arlo_venue_list_pagination]
    </div>
    [arlo_powered_by]
    
</div>
