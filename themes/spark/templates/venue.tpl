<div class="arlo" id="arlo">

	[arlo_venue_map height="100" width="640" wrap='<div class="arlo-venue-map">%s</div>']

	<div class="arlo-venue-details">
	    [arlo_label label='<h4>Address</h4>']
		    [arlo_venue_address layout="string" items="line1" wrap="%s"]
		    [arlo_venue_address layout="string" items="line2" wrap="<br>%s"]
		    [arlo_venue_address layout="string" items="line3" wrap="<br>%s"]
		    [arlo_venue_address layout="string" items="line4" wrap="<br>%s"]
		    [arlo_venue_address layout="string" items="suburb,city" wrap="<br>%s"]
		    [arlo_venue_address layout="string" items="state" wrap="%s"]
		    [arlo_venue_address layout="string" items="post_code" wrap="%s"]
		    [arlo_venue_address layout="string" items="country" wrap="<br>%s"]
	    [/arlo_label]

	    [arlo_venue_directions label='<h4>Directions</h4>']
	    [arlo_venue_parking label='<h4>Parking</h4>']
	</div>
    [arlo_powered_by]
</div>
[arlo_venue_rich_snippet]