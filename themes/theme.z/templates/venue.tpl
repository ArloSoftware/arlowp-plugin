<div id="arloapp" class="arlo-desktop">
<div class="arlo-wrapper arlo-venue" id="arlo">
    <div class="arlo-venue-basic">
        <div class="arlo-venue-basic-name">
            <h1>[arlo_venue_name]</h1>
            [arlo_venue_direction wrap='<a target="_blank" class="arlo-button" href="%s" role="button">Get directions</a>']
        </div>
        <div class="arlo-venue-basic-address arlo-venue-group">
            <h2><span class="arlo-icon-wrapper"><i class="fa-solid fa-location-dot"></i></span>Address</h2>
            <p>
                [arlo_venue_address layout="string" items="line1" wrap="%s<br>"]
                [arlo_venue_address layout="string" items="line2" wrap="%s<br>"]
                [arlo_venue_address layout="string" items="line3" wrap="%s<br>"]
                [arlo_venue_address layout="string" items="line4" wrap="%s<br>"]
                [arlo_venue_address layout="string" items="locale" wrap="%s<br>"]
                [arlo_venue_address layout="string" items="country" wrap="%s"]
            </p>
        </div>
    </div>
    [arlo_venue_map]
    <div class="arlo-venue-info">
        <div class="arlo-venue-info-direction arlo-venue-group">
            [arlo_venue_directions wrap='<h2><span class="arlo-icon-wrapper"><i class="fa-solid fa-diamond-turn-right"></i></span>Directions</h2>%s']
        </div>
        <div class="arlo-venue-info-parking arlo-venue-group">
            [arlo_venue_parking wrap='<h2><span class="arlo-icon-wrapper"><i class="fa-solid fa-square-parking"></i></span>Parking</h2>%s']
        </div>
    </div>
</div>
</div>