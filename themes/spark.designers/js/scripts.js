jQuery(function ($) {

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

    var arloContainer = '#arlo.arlo';

    setWidthOfArlo(arloContainer);

    $(window).resize(function () {
        setWidthOfArlo(arloContainer);
    });
});
