jQuery(function($){ 
    var $arloContainer = $("#arloapp");
    var arloScreentMode = ''
    var catalogheaderDefaultHegith = $(".arlo-catalog-header").height()

    var setPageSize = function () {
        console.log('resizing')
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
                $(".arlo-catalog-moreheader").css('display', 'inline-flex')
            } else {
                $(".arlo-catalog-moreheader").hide();
            }
        }
    }

    //Catalogue Page Function
    (function catalogPage() {
        resetCatalogSeeMore();
        $(".arlo-catalog-moreheader").click(function() {
            var height = $(".arlo-catalog-header").height()
            if(height != catalogheaderDefaultHegith) {
                $(".arlo-catalog-header").css('height', catalogheaderDefaultHegith + 'px')
                $('.arlo-catalog-moreheader .fa-plus').show()
                $('.arlo-catalog-moreheader .fa-minus').hide()
                $('.arlo-catalog-moreheader span').text('See more')
            }else {
                $(".arlo-catalog-header").css('height', 'auto')
                $('.arlo-catalog-moreheader .fa-plus').hide()
                $('.arlo-catalog-moreheader .fa-minus').show()
                $('.arlo-catalog-moreheader span').text('See less')
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
            console.log('trigger')
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
            if(hasCategoryFilter || hasDeliveryFfilter || hasLoactionFilter) {
                $(".arlo-catalog-mobild-filter").trigger('click')
            }
        }
    })()















    //upcoming event
    $(".arlo-form-control-arrow").click(function() {
        $(this).parent().next().slideToggle();
        if($(this).hasClass('fa-chevron-up')) {
            $(this).next().show()
        } else {
            $(this).prev().show()
        }
        $(this).hide()
    })

    //presenter
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

    //event
    $(".arlo-show-sessions").click(function() {
        var target = $(`.arlo_session_modal_${$(this).data('id')}`)
        target.show();
    })
    $(".arlo-sessions-close").click(function() {
        $('.arlo-sessions-popup-component').hide();
    })

    //upcoming
    $(".arlo-form-control-input.checkbox input").click(function() {
        $(this).parent().toggleClass('active')
    })
    $(".arlo-upcoming-filter-icon").click(function() {
        $('.arlo-upcoming-filter-wrapper').show();
    })
    $("#searchkey").keyup(function() {
        if($(this).val()) {
            $(this).parent().addClass('active')
            $(this).siblings('.clear').show()
        } else {
            $(this).parent().removeClass('active')
            $(this).siblings('.clear').hide()
        }
    })
    $(".clear").click(function() {
        $("#searchkey").val('').trigger('keyup')
    })
});