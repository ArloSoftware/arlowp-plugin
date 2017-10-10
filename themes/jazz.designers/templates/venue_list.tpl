<div class="arlo" id="arlo">

<ul class="arlo-list venues">
    [arlo_venue_list_item]
        <li class="arlo-cf arlo-border-color2">
            <h2>[arlo_venue_permalink wrap="<a href='%s'>"][arlo_venue_name]</a></h2>
                [arlo_venue_address layout="string" items="line1" wrap="%s"]
                [arlo_venue_address layout="string" items="line2" wrap="<br>%s"]
                [arlo_venue_address layout="string" items="line3" wrap="<br>%s"]
                [arlo_venue_address layout="string" items="line4" wrap="<br>%s"]
                [arlo_venue_address layout="string" items="suburb,city" wrap="<br>%s"]
                [arlo_venue_address layout="string" items="state" wrap="%s"]
                [arlo_venue_address layout="string" items="post_code" wrap="%s"]
                [arlo_venue_address layout="string" items="country" wrap="<br>%s"]
                [arlo_venue_parking label='<h5>Parking</h5>']
                [arlo_venue_directions label='<h5>Directions</h5>']
        </li>
        [arlo_venue_rich_snippet]
    [/arlo_venue_list_item]
</ul>

[arlo_venue_list_pagination wrap='<div class="arlo-pagination">%s</div>']
[arlo_powered_by]

</div>
