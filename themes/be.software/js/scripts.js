jQuery(function($){ 
    // add in delivery tag icons
    var deliveryTags = jQuery('.arlo-delivery');

    deliveryTags.each(function(index,tag) { 
        var content = ""; 
        $tag = jQuery(tag); 
        if ($tag.hasClass('arlo-web_privateonsite')) { content += "  "; } 
        if ($tag.hasClass('arlo-web_public')) { content += "  "; } 
        if ($tag.hasClass('arlo-web_liveonline')) { content += "  "; } 
        if ($tag.hasClass('arlo-web_selfpacedonline')) { content += "  "; } 
        $tag.find('.arlo-delivery-icons').html(content); 
    })

    var setEventHeights = function() {
        // find the tallest event and set other events to have that height
        var events = jQuery('.events .arlo-cf, .catalogue .arlo-cf.arlo-catalogue-event');
        var tallest = 0; 
        
        // some events are hidden behind a 'show more', need to show them temporarily so we can see their heights
        jQuery(".events.arlo-show-more-hidden").toggle();

        events.css('min-height','auto');

        events.each(function(index,event){ 
            var height = jQuery(event).height(); 
            if ( height > tallest ) { tallest = height };
        });

        jQuery(".events.arlo-show-more-hidden").toggle();

        // set events to tallest height + padding
        events.css('min-height',tallest + 40);
    }

    setEventHeights();
    $(window).resize(setEventHeights)
 });