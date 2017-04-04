jQuery(function($){ 
    var deliveryTags = jQuery('.arlo-delivery');

    deliveryTags.each(function(index,tag) { 
        // add in delivery tag icons
        var content = ""; 
        $tag = jQuery(tag); 
        if ($tag.hasClass('arlo-web_privateonsite')) { content += "  "; } 
        if ($tag.hasClass('arlo-web_public')) { content += "  "; } 
        if ($tag.hasClass('arlo-web_liveonline')) { content += "  "; } 
        if ($tag.hasClass('arlo-web_selfpacedonline')) { content += "  "; } 
        $tag.find('.arlo-delivery-icons').html(content); 
    })



    var setNumberOfEventColumns = function (container) {
        // set number of event columns based on the container width
        var $container = jQuery(container);
        var $containerWidth = $container.width();

        $container.removeClass('arlo-cols-5 arlo-cols-4 arlo-cols-3 arlo-cols-2 arlo-cols-1');

        if ($containerWidth < 1900) {
            $container.addClass('arlo-cols-5');
        } if ($containerWidth < 1600) {
            $container.addClass('arlo-cols-4');
        } if ($containerWidth < 1200) {
            $container.addClass('arlo-cols-3');
        } if ($containerWidth < 960) {
            $container.addClass('arlo-cols-2');
        } if ($containerWidth < 670) {
            $container.addClass('arlo-cols-1');
        }
    }

    setNumberOfEventColumns('ul.arlo-list.catalogue, ul.arlo-list.events, ul.arlo-list.event-search-list');

    jQuery(window).resize(function() {
        setNumberOfEventColumns('ul.arlo-list.catalogue, ul.arlo-list.events, ul.arlo-list.event-search-list');
    });



    var setEventHeights = function() {
        // find the tallest event and set other events to have that height
        var $events = jQuery('.catalogue > li.arlo-cf.arlo-catalogue-event, .arlo-list.events > li.arlo-cf, ul.arlo-list.event-search-list > li.arlo-cf');
        
        // some events might be hidden behind a 'show more', need to show them temporarily so we can see their heights
        jQuery(".events.arlo-show-more-hidden").show();

        $events.css('min-height','auto');
        
        var tallest = 0; 
        $events.each(function(index,event){ 
            var height = jQuery(event).height(); 
            if ( height > tallest ) { tallest = height };
        });

        jQuery(".events.arlo-show-more-hidden").hide();

        // set events to tallest height + padding
        $events.css('min-height',tallest + 60);
    }

    setEventHeights();
    
    jQuery(window).resize(setEventHeights);
 });