<div class="arlo" id="arlo">

<ul class="arlo-list venues">
    [arlo_venue_list_item]
        <li class="arlo-cf arlo-border-color2">
            <h2>[arlo_venue_permalink wrap="<a href='%s'>"][arlo_venue_name]</a></h2>

                [arlo_venue_address layout="string" items="line1,line2,line3,line4,suburb,city" wrap="<p>%s</p>"]
                [arlo_venue_parking label='<h5>Parking</h5>']
                [arlo_venue_directions label='<h5>Directions</h5>']

                [arlo_venue_map height="200" width="250"]
        </li>
    [/arlo_venue_list_item]
</ul>

[arlo_venue_list_pagination limit="20" wrap='<div class="arlo-pagination">%s</div>']

</div>