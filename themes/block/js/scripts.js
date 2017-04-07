jQuery(function($){ 

    var setEventHeights = function() {
        // find the tallest event and set other events to have that height
        var $events = $('.arlo .events > .arlo-event, .arlo .catalogue .arlo-catalogue-event');
        
        // some events might be hidden behind a 'show more', need to show them temporarily so we can see their heights
        $(".arlo .events.arlo-show-more-hidden").show();

        $events.css('min-height','auto');
        
        var tallest = 0; 
        $events.each(function(index,event){ 
            var height = $(event).outerHeight(); 
            if ( height > tallest ) { tallest = height };
        });

        $(".arlo .events.arlo-show-more-hidden").hide();

        // set events to tallest height
        $events.css('min-height',tallest + 10);
    }

    setTimeout(setEventHeights, 0)
    
    $(window).resize(setEventHeights);


    var setNumberOfEventColumns = function (container) {
        // set number of event columns based on the container width
        var $container = $(container);
        var $containerWidth = $container.width();

        $container.removeClass('arlo-cols-5 arlo-cols-4 arlo-cols-3 arlo-cols-2 arlo-cols-1');

        if ($containerWidth < 1900) {
            $container.addClass('arlo-cols-5');
        } if ($containerWidth < 1600) {
            $container.addClass('arlo-cols-4');
        } if ($containerWidth < 1200) {
            $container.addClass('arlo-cols-3');
        } if ($containerWidth < 850) {
            $container.addClass('arlo-cols-2');
        } if ($containerWidth < 670) {
            $container.addClass('arlo-cols-1');
        }
    }

    var eventsContainer = '.arlo .events, .arlo .catalogue';

    setNumberOfEventColumns(eventsContainer);

    $(window).resize(function() {
        setNumberOfEventColumns(eventsContainer);
    });
    
 });