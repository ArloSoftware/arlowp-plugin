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

    var selectors = 'ul.arlo-list.catalogue, ul.arlo-list.events';

    setNumberOfEventColumns(selectors);

    jQuery(window).resize(function() {
        setNumberOfEventColumns(selectors);
    });



    function setHeightForSubset(subset) {
        var tallest = 0;
        $(subset).each(function(index, event){ 
            var height = $(event).outerHeight(); 
            if ( height > tallest ) { tallest = height };
        });
        // set events to tallest height + padding
        $(subset).css('min-height', tallest + 10);
    }

    var setEventHeights = function() {
        // find the tallest event and set other events to have that height
        var $events = jQuery('.catalogue > li.arlo-cf.arlo-catalogue-event, .arlo-list.events > li.arlo-cf');
        
        // some events might be hidden behind a 'show more', need to show them temporarily so we can see their heights
        jQuery(".events.arlo-show-more-hidden").show();

        $events.css('min-height','auto');
        
        // set same height per line
        if ($events.length) {
            var modulo = 99;
            var parent = $events.first().parent();
            if (parent.hasClass('arlo-cols-5')) { modulo = 5; }
            if (parent.hasClass('arlo-cols-4')) { modulo = 4; }
            if (parent.hasClass('arlo-cols-3')) { modulo = 3; }
            if (parent.hasClass('arlo-cols-2')) { modulo = 2; }
            if (parent.hasClass('arlo-cols-1')) { modulo = 1; }

            var eventsSubset = [];
            for (var i = 0; i < $events.length; i++) {
                eventsSubset.push($events[i]);

                var zeroRemainder = (eventsSubset.length % modulo === 0); //https://stackoverflow.com/questions/16505559/how-can-i-use-modulo-operator-in-javascript
                var successorIsDivider =  $($events[i]).nextAll('li:first').hasClass('arlo-group-divider');

                // adjust heights per row
                if (zeroRemainder || successorIsDivider) {
                    setHeightForSubset(eventsSubset);
                    eventsSubset = [];
                }
            }
            setHeightForSubset(eventsSubset);
        }

        if(jQuery(".arlo .arlo-show-more-link-container").length > 0) {
            jQuery(".events.arlo-show-more-hidden").hide();
        }
    }

    setEventHeights();
    
    jQuery(window).resize(setEventHeights);
 });
