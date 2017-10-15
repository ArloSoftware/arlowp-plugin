<div class="arlo" id="arlo">

    <ul class="arlo-list venues">
        [arlo_venue_list_item]
            <li class="arlo-venue-listitem arlo-cf">

                <h2 class="arlo-venue-name">[arlo_venue_permalink wrap='<i class="icons8-marker"></i><a href="%s">'][arlo_venue_name]</a></h2>

                <div class="arlo-venue-city">
                    [arlo_venue_address layout="string" items="suburb,city,state,country" wrap='%s']
                </div>

                [arlo_venue_permalink wrap='<div class="arlo-venue-info-link"><a href="%s">View all venue information</a></div>']

            </li>
            [arlo_venue_rich_snippet]
        [/arlo_venue_list_item]
    </ul>

    <div class="arlo-pagination">
        [arlo_venue_list_pagination]
    </div>
    [arlo_powered_by]
    
</div>
