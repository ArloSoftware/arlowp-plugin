jQuery(function ($) {

    var arloContainer = '#arlo.arlo';

    var setEventHeights = function() {
        // find the tallest event and set other events to have that height
        var $events = $('.arlo .events > .arlo-event');
        if($(arloContainer).width() < 768) {
            $events.css('min-height','auto');
            return;
        }

        // some events might be hidden behind a 'show more', need to show them temporarily so we can see their heights
        $(".arlo .events.arlo-show-more-hidden").show();

        $events.css('min-height','auto');

        var tallest = 0;
        $events.each(function(index,event){
            var height = $(event).outerHeight();
            if ( height > tallest ) { tallest = height };
        });

        if($(".arlo .arlo-show-more-link-container").length > 0) {
            $(".arlo .events.arlo-show-more-hidden").hide();
        }

        // set events to tallest height + padding
        $events.css('min-height',tallest + 10);
    }

    setTimeout(setEventHeights, 0);

    $(window).resize(setEventHeights);

    var setWidthOfArlo= function (container) {
        // set class of Arlo module based on container width
        var $container = $(container);
        var $containerWidth = $container.width();

        $container.removeClass('arlo-lg arlo-md arlo-sm arlo-xs');

        if ($containerWidth < 1200) {
            $container.addClass('arlo-lg');
        }
        if ($containerWidth < 768) {
            $container.addClass('arlo-md');
        }
        if ($containerWidth < 550) {
            $container.addClass('arlo-sm');
        }
        if ($containerWidth < 420) {
            $container.addClass('arlo-xs');
        }
    }


    setWidthOfArlo(arloContainer);

    $(window).resize(function () {
        setWidthOfArlo(arloContainer);
    });
});
