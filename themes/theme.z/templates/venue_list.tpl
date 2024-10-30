<div id="arloapp" class="arlo-desktop">
<div class="arlo-wrapper arlo-venue-list" id="arlo" role="list">
    [arlo_venue_list_item limit="6"]
        <div class="arlo-venue-list-item" role="listitem">
            <div>
                [arlo_venue_map placeholder="themes/theme.z/images/nomap.jpg"]
                <div class="arlo-venue-list-item-address">
                    <h2 class="arlo-venue-list-item-address-name">[arlo_venue_permalink wrap="<a href='%s'>"][arlo_venue_name]</a></h2>
                    <div class="arlo-venue-list-item-address-detail">
                        <i class="fa-solid fa-location-dot"></i>
                        <p class="m-l-20">
                            [arlo_venue_address layout="string" items="line1,line2,line3,line4,locale,country" wrap="%s"]
                        </p>
                    </div>
                </div>
            </div>
            [arlo_venue_permalink wrap="<a href='%s' class='arlo-button' role='button'>"]Learn more</a>
        </div>
    [/arlo_venue_list_item]
</div>

<div class="arlo-pager">
    [arlo_venue_list_pagination limit="6"]
</div>
 <div class="arlo-align-center-row arlo-mobile-padding">
    <button class="arlo-button arlo-gray arlo-button-morevenue">Show more</button>
</div>


<div class="arlo-wrapper">
[arlo_powered_by]
</div>
</div>