<div class="arlo" id="arlo">

<ul class="arlo-list venues">
    [arlo_venue_list_item]
        <li class="arlo-cf">
            <h2>[arlo_venue_permalink wrap="<a href='%s' class='arlo-color1'>"][arlo_venue_name]</a></h2>
			    [arlo_venue_address layout="string" items="line1" wrap="%s"]
			    [arlo_venue_address layout="string" items="line2" wrap="<br>%s"]
			    [arlo_venue_address layout="string" items="line3" wrap="<br>%s"]
			    [arlo_venue_address layout="string" items="line4" wrap="<br>%s"]
			    [arlo_venue_address layout="string" items="suburb,city" wrap="<br>%s"]
			    [arlo_venue_address layout="string" items="state" wrap="%s"]
			    [arlo_venue_address layout="string" items="post_code" wrap="%s"]
			    [arlo_venue_address layout="string" items="country" wrap="<br>%s"]

				[arlo_venue_parking label='<h5><i class="icons8-parking"></i> Parking</h5>']
				[arlo_venue_directions label='<h5><i class="icons8-compass"></i> Directions</h5>']
        </li>
        [arlo_venue_rich_snippet]
    [/arlo_venue_list_item]
</ul>

[arlo_venue_list_pagination wrap='<div class="arlo-pagination">%s</div>']
[arlo_powered_by]

</div>
