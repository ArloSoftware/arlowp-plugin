jQuery(function($){ 
    var $arloContainer = $("#arloapp");
    var arloScreentMode = ''
    var catalogheaderDefaultHegith = $(".arlo-catalog-header").height()

    var setPageSize = function () {
        // console.log('resizing')
        // set number of event columns based on the container width
        var containerWidth = $arloContainer.width();
        var cls = 'arlo-desktop'
        if(containerWidth <= 1200) {
            if (containerWidth <= 1200) {
                cls += ' arlo-pad'
            }
            if (containerWidth <= 720) {
                cls += ' arlo-mobile'
            }
        } 
        if(arloScreentMode != cls) {
            $arloContainer.removeClass('arlo-pad arlo-mobile').addClass(cls);
            arloScreentMode = cls
            //on page size changed
            if($arloContainer.width() <= 720){
                catalogheaderDefaultHegith = 95
                if(!$(".arlo-catalog-mobild-filter").hasClass('outline')) { //open
                    $(".arlo-catalog-mobild-filter").trigger('click');
                } else {
                    $('.arlo-catalog-filters').hide();
                }
            } else if($arloContainer.width() > 720 && $arloContainer.width() <= 1200) {
                catalogheaderDefaultHegith = 71
                $('.arlo-catalog-filters').show();
            } else {
                catalogheaderDefaultHegith = 46
                $('.arlo-catalog-filters').show();
            }

            //reset set more
            $(".arlo-catalog-header").css('height', catalogheaderDefaultHegith + 'px')
            $('.arlo-catalog-moreheader .fa-plus').show()
            $('.arlo-catalog-moreheader .fa-minus').hide()
            $('.arlo-catalog-moreheader span').text('See more')
            resetCatalogSeeMore();
        }
        arloInitDiscountIcon()
    }
    $(window).resize(function() {
        setPageSize();
    });
    setPageSize();
    
    function resetCatalogSeeMore() {
        var $catalogheader = $(".arlo-catalog-header");
        if($catalogheader.length > 0) {
            var more = catalogheaderDefaultHegith < $catalogheader.get(0).scrollHeight;
            if(more) {
                $(".arlo-catalog-moreheader").show()
                $(".arlo-catalog-moreheader").data('showing', 'false')
            } else {
                $(".arlo-catalog-moreheader").hide();
            }
        }
    }

    function mobileFilterFunc($btn, $filter) {
        $btn.click(function() {
            var status = $(this).data('status')
            if(status == 'open') { //close
                $filter.slideUp('fast');
                $(this).data('status', 'close')
                $(this).find('.fa-sliders').show()
                $(this).find('.fa-xmark').hide()
                $(this).addClass('gray').addClass('outline')
            } else {
                $filter.slideDown('fast');
                $(this).data('status', 'open')
                $(this).find('.fa-sliders').hide()
                $(this).find('.fa-xmark').show()
                $(this).removeClass('gray').removeClass('outline')
            }
        })
    }

    //Catalogue Page Function
    function catalogPageFunction() {
        resetCatalogSeeMore();
        $(".arlo-catalog-moreheader").click(function() {
            var showing = $(this).data('showing')
            if(showing === 'true') {
                $(".arlo-catalog-header").css('height', catalogheaderDefaultHegith + 'px')
                $('.arlo-catalog-moreheader .fa-plus').show()
                $('.arlo-catalog-moreheader .fa-minus').hide()
                $('.arlo-catalog-moreheader span').text('See more')
                $(this).data('showing', 'false')
            }else {
                $(".arlo-catalog-header").css('height', 'auto')
                $('.arlo-catalog-moreheader .fa-plus').hide()
                $('.arlo-catalog-moreheader .fa-minus').show()
                $('.arlo-catalog-moreheader span').text('See less')
                $(this).data('showing', 'true')
            }
        })
        //navbar
        $('.arlo-catalog-filters-nav span').click(function() {
            if($(this).css('cursor') !== 'pointer') { return; }
            var navIdx = $(this).index();
            var slug = $(this).data('slug')
            var paths = location.pathname.split('/');
            var idx = 0;
            paths.forEach(path => {
                if(path.startsWith('cat-')) {
                    if(navIdx == 0) {
                        paths[idx] = ''
                    } else {
                        paths[idx] = 'cat-' + slug
                    }
                }
                idx++;
            })
            paths.filter(x=>x != '')
            var href = paths.join('/')
            location.href = href
        })
        //filter
        $(".arlo-catalog-mobild-filter").click(function() {
            // console.log('trigger')
            var status = $(this).data('status')
            if(status == 'open') { //close
                $(".arlo-catalog-filters").slideUp('fast');
                $(this).data('status', 'close')
                $(this).find('.fa-sliders').show()
                $(this).find('.fa-xmark').hide()
                $(this).addClass('gray').addClass('outline')
            } else {
                $(".arlo-catalog-filters").slideDown('fast');
                $(this).data('status', 'open')
                $(this).find('.fa-sliders').hide()
                $(this).find('.fa-xmark').show()
                $(this).removeClass('gray').removeClass('outline')
            }
        })
        //init filter bar
        if($arloContainer.width() <= 720) {
            var hasCategoryFilter = $("#arlo-filter-category").val()
            var hasDeliveryFfilter = $("#arlo-filter-delivery").val()
            var hasLoactionFilter = $("#arlo-filter-location").val()
            var hasTagFilter = $("#arlo-filter-templatetag").val()
            if(hasCategoryFilter || hasDeliveryFfilter || hasLoactionFilter || hasTagFilter) {
                $(".arlo-catalog-mobild-filter").trigger('click')
            }
        }
    }
  
    //event
    function evnetPageFunction() {
        arloAppenCloseIconToModal()

        mobileFilterFunc($(".arlo-event-filter-icon"), $(".arlo-event-list-filter"))
        $(".arlo-event-more-icon").click(function() {
            var status = $(this).data('status')
            var $info = $(this).parent().parent().find('.arlo-event-list-items-item-info');
            var $parent = $(this).parent().parent().parent();
            if(status == 'open') { //close
                $(this).data('status', 'close')
                $(this).find('.fa-chevron-up').hide()
                $(this).find('.fa-chevron-down').show()
                $parent.removeClass('open')
                $info.css('height', "90px")
  
            } else {
                $(this).data('status', 'open')
                $(this).find('.fa-chevron-up').show()
                $(this).find('.fa-chevron-down').hide()
                $parent.addClass('open')
                $info.css('height', $info.get(0).scrollHeight + 'px')

            }
        })
        $(".arlo-button-book-now").click(function() {
            if($('.arlo-event-list .arlo-event-list-items-item').length > 0)
                $('.arlo-event-list').get(0).scrollIntoView({ behavior: 'smooth'})
            else 
                $('.arlo-no-results').get(0).scrollIntoView({ behavior: 'smooth'})
        })
        //clear empty dom
        var noLink  = false;
        if(!$(".arlo-event-links").html().trim()) {
            $(".arlo-event-links").hide();
            noLink = true
        }
        if($('.arlo-event-list .arlo-event-list-items-item').length == 0) {
            $('.arlo-filter-mobile').remove();
        }
        //auto scroll to events
        function isLocationChanged() {
            var currentLocationPart = location.pathname.split('/').find(function(p) { 
                return p.startsWith('location-');
            })
            var oldLocationPart = document.referrer?.split('/').find(function(p) { 
                return p.startsWith('location-');
            })
            return currentLocationPart != oldLocationPart
        }
        function isTimezoneChanged() {
            var currentTimezonePart = new URLSearchParams(window.location.search).get("timezone")
            var oldTimezonePart = null
            if(document.referrer && document.referrer.indexOf('?') >= 0) {
                oldTimezonePart = new URLSearchParams("?" + document.referrer.split('?')[1]).get("timezone")
            }
            return currentTimezonePart != oldTimezonePart
        }
        if(isLocationChanged() || isTimezoneChanged()) {
            if(localStorage.getItem('event_scroll_top')) {
                document.querySelector("html").scroll(0, localStorage.getItem('event_scroll_top'))
                localStorage.removeItem('event_scroll_top')
            }
        }
        
        $( window ).on('unload', function( event ) {
            var top = document.querySelector("html").scrollTop
            localStorage.setItem('event_scroll_top' , top)
        });
        
    }
 
    //upcoming
    function upcomingPageFunction() {
        arloAppenCloseIconToModal()
        $(".arlo-form-control-input.checkbox input").click(function() {
            $(this).parent().toggleClass('active')
        })
        $(".arlo-upcoming-filter-icon").click(function() {
            $('.arlo-upcoming-filter-wrapper').show();
        })
        // $("#searchkey").keyup(function() {
        //     if($(this).val()) {
        //         $(this).parent().addClass('active')
        //         $(this).siblings('.clear').show()
        //     } else {
        //         $(this).parent().removeClass('active')
        //         $(this).siblings('.clear').hide()
        //     }
        // })
        // $(".clear").click(function() {
        //     $("#searchkey").val('').trigger('keyup')
        // })
        //filter
        $(".arlo-upcoming-filter-icon").click(function() {
            var status = $(this).data('status')
            if(status == 'open') { //close
                $(".arlo-upcoming-filter").slideUp('fast');
                $(this).data('status', 'close')
                $(this).find('.fa-sliders').show()
                $(this).find('.fa-xmark').hide()
                $(this).addClass('gray').addClass('outline')
            } else {
                $(".arlo-upcoming-filter").slideDown('fast');
                $(this).data('status', 'open')
                $(this).find('.fa-sliders').hide()
                $(this).find('.fa-xmark').show()
                $(this).removeClass('gray').removeClass('outline')
            }
        })
        //init filter bar
        if($arloContainer.width() <= 720) {
            var hasCategoryFilter = $("#arlo-filter-category").val()
            var hasDeliveryFfilter = $("#arlo-filter-month").val()
            var hasLoactionFilter = $("#arlo-filter-location").val()
            if(hasCategoryFilter || hasDeliveryFfilter || hasLoactionFilter) {
                $(".arlo-filter-upcoming-mobile").trigger('click')
            }
        }
    }

    //event search
    function eventSearchPageFuncion() {
        //highlight
        var text = $(".arlo-search-field").val();
        if(text) {
            var keys = text.split(' ')
            var finalkeys = []
            keys.forEach(key => {
                if(!finalkeys.find(x => x.toLowerCase().indexOf(key.toLowerCase()) >= 0)) {
                    finalkeys.push(key)
                }
            })
            var $targets = $(".arlo-event-search-result-item-title,.arlo-event-search-result-item-desc");
            $targets.each(function() {
                var html = $(this).html();
                finalkeys.forEach(function(key) {
                    const matches = html.match(new RegExp(key, 'ig')) || []
                    matches.forEach(match => {
                        html = html.replaceAll(match, '<strong>' + match + '</strong>')
                    })
                })
                $(this).html(html)
            })
        }
        
    }

    //presenter
    function presenterPageFunction() {
        if($('.arlo-presenter').length > 0) {
            var arlo_presenter_events_swiper = null;
            function arlo_init_swiper() {
                if(typeof Swiper == 'undefined')  return;
                if(arlo_presenter_events_swiper != null) {
                    arlo_presenter_events_swiper.destroy();
                }
                var spacing = 26
                if(window.innerWidth > 720 && window.innerWidth < 1200) spacing = 18;
                else if(window.innerWidth <= 720) spacing = 16
                arlo_presenter_events_swiper = new Swiper ('.swiper', {
                    loop: true, 
                    slidesPerView:  'auto',
                    spaceBetween: spacing,
                })
            }
            $(window).resize(function() {
                arlo_init_swiper();
             });
             arlo_init_swiper();
        }
        var height = 0;
        $(".arlo-events-item").each(function() {
            if($(this).height() > height) height = $(this).height()
        })
        $(".arlo-events-item").each(function() {
            $(this).height(height)
        })
    }


    function venuePageFunction() {
        var direction = $(".arlo-venue-info-direction").html().trim()
        var parking = $(".arlo-venue-info-parking").html().trim()
        if(!direction){
            $(".arlo-venue-info-direction").remove();
            $(".arlo-venue-group").addClass('arlo-onecol')
        }
        if(!parking){
            $(".arlo-venue-info-parking").remove();
            $(".arlo-venue-group").addClass('arlo-onecol')
        }
    }

    const pageFuncConfig = {
        '.arlo-event-search' : eventSearchPageFuncion,
        '.arlo-catelog-page' : catalogPageFunction,
        '.arlo-schedule-page': catalogPageFunction,
        '.arlo-event-page' : evnetPageFunction,
        '.arlo-presenter' :  presenterPageFunction,
        '.arlo-upcoming'  : upcomingPageFunction,
        '.arlo-venue'     : venuePageFunction
    }
    for(var cls in pageFuncConfig) {
        if($(cls).length > 0) pageFuncConfig[cls]();
    }
});