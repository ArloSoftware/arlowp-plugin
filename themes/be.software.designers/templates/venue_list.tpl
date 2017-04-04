<div class="arlo" id="arlo">

    <ul class="arlo-list venues">
        [arlo_venue_list_item]
            <li class="arlo-cf">
                <h2>[arlo_venue_permalink wrap="<a href='%s'>"][arlo_venue_name]</a></h2>
                [arlo_venue_map height="120" width="640"]

                [arlo_venue_permalink wrap="<a href='%s' class='arlo-venue-info-link'>"]View all venue information</a>
                [arlo_venue_address layout="string" items="line1,line2,line3,line4,suburb,city" wrap='<div class="arlo-venue-address arlo-venue-content">%s</div>']
                [arlo_venue_parking wrap='<div class="arlo-venue-parking arlo-venue-content"><h4>Parking</h4>%s</div>']
                [arlo_venue_directions wrap='<div class="arlo-venue-directions arlo-venue-content"><h4>Directions</h4>%s</div>']

            </li>
        [/arlo_venue_list_item]
    </ul>

    <div class="arlo-pagination">
        [arlo_venue_list_pagination]
    </div>

</div>