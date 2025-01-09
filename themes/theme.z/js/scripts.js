//Common functions
function arloInitDiscountIcon() {
    if(jQuery(".arlo-mobile").length > 0) {
        jQuery('.arlo-event-list-items-item').each(function() {
        if(jQuery(this).find('.arlo-event-offers .discount').length > 0) {
            jQuery(this).find('.arlo-event-list-items-item-time-detail-discount').css('display', 'flex')
        } else {
            jQuery(this).find('.arlo-event-list-items-item-time-detail-discount').css('display', 'none')
        }
        })
    }
}
function arloAppenCloseIconToModal() {
    jQuery(".arlo-sessions-popup-header").each(function() {
        if(jQuery(this).find('span').length == 0) {
            jQuery(this).append("<span class='arlo-session-close'><i class='fa fa-times'></i></span>")
        }
    })
    jQuery('body').delegate('.arlo-session-close', 'click', function() {
        jQuery(".tingle-modal__close").each(function() {this.click()});
    })
}


//Dom logic
jQuery(function($) { 
    var $arloContainer = $("#arloapp");
    var arloScreentMode = ''
    var catalogheaderDefaultHegith = $(".arlo-catalog-header").height()

    var setPageSize = function () {
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
                if(!$(".arlo-catalog-mobild-filter").hasClass('outline')) { //open
                    $(".arlo-catalog-mobild-filter").trigger('click');
                } else {
                    $('.arlo-catalog-filters').hide();
                }
            } else if($arloContainer.width() > 720 && $arloContainer.width() <= 1200) {
                $('.arlo-catalog-filters').show();
            } else {
                $('.arlo-catalog-filters').show();
            }

            //reset set more button
            $('.arlo-catalog-moreheader .fa-plus').show()
            $('.arlo-catalog-moreheader .fa-minus').hide()
            $('.arlo-catalog-moreheader span').text('See more')
            $(".arlo-catalog-header").removeClass("arlo-showmore")
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
            catalogheaderDefaultHegith = $(".arlo-catalog-header").css('height').replace('px', '');
            var more = parseFloat(catalogheaderDefaultHegith) < $catalogheader.get(0).scrollHeight;
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
                $(".arlo-catalog-header").addClass('arlo-showmore')
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

//Data integration
jQuery(function($){ 
    //common functions
    function hasNextPage() {
        return $(".arlo-pager .next.page-numbers").length > 0
    }
    function getNextPageLink() {
        return $(".arlo-pager .next.page-numbers").attr('href')
    }
    if(!hasNextPage()) {
        $(".arlo-btn-more-category").parent().hide();
    }
    function initLoadMoreButton($btn) {
        if(!hasNextPage()) {
            $btn.parent().hide();
        }
    }
    function loadMore($morebtn) {
        return new Promise(function(resolve,_) {
            $morebtn.attr('disabled', 'disabled')
            if(hasNextPage()) {
                $.get(getNextPageLink(), function(html) {
                    //update pager
                    var pagerHtml = $(html).find('.arlo-pager').html()
                    $(".arlo-pager").html(pagerHtml);
                    $morebtn.removeAttr('disabled');
                    //handle response html
                    resolve(html) 
                    if(!hasNextPage()) {
                        $morebtn.parent().hide(); //hide load more ,if there is no next link
                    }
                })
            }
        })
    }
    function initListPageFunc(listcls, $morebtn, then) {
        $wrapper = $("." + listcls);
        if($wrapper.length == 0) return;
        initLoadMoreButton($morebtn);
        $morebtn.click(function() {
            loadMore($morebtn).then(function(html) {
                var $newlist = $($(html).find('.' + listcls).html())
                $("." + listcls).append($newlist);
                then && then($newlist);
            });
        })
    }
    //catalogue
    $('body').delegate('.arlo-btn-more-event', 'click', function() {
        $(this).attr('disabled', 'disabled')
        var $button = $(this);
        var currentPage = $(this).data('page')
        if(!currentPage) {
            currentPage = 1;
        } else {
            currentPage = parseInt(currentPage)
        }
        currentPage+=1;
        var slug = $(this).data('slug')
        
        var filters = {
            'cat': 'arlo-filter-category',
            'location': 'arlo-filter-location',
            'delivery': 'arlo-filter-delivery',
            'templatetag': 'arlo-filter-templatetag',
        };
        var page = $('#arlo-page').val();
        if (page[page.length-1] != '/') {
            page = page + '/';
        }
        if ($('#arlo-filter-region').length > 0) {
            page += 'region-' + $('#arlo-filter-region').val() + '/';
        }
        var url = page;
        var urlParams = ['pagefor=event', 'epage=' + currentPage];

        for (var i in filters) {
            if (filters.hasOwnProperty(i) && $('#' + filters[i]).length == 1) {
                var content = $('#' + filters[i]).val().trim();
                if(i=='cat') {
                    content = slug
                }
                if (content != '') {
                    url += i + '-' + encodeURIComponent(content) + '/'; 
                }
            } 
        }

        if (urlParams.length > 0) {
            url += '?' + urlParams.join('&');
        }
        console.log(url);
        $.get(url, function(html) {
            var $eventItems =$(html).find(eventsSelector);
            var $eventList = $button.parent().prev();
            var idx = 0;
            $eventItems.each(function() {
                if(idx >= 3) return
                $(this).removeClass('hide')
                $eventList.append($(this));
                idx++;
            })
            if($eventItems.length <= 3) {
                $button.parent().hide(); //hide load more ,if there is no next link
            }
            $button.data('page', currentPage)
            $button.removeAttr('disabled');
        })
    })
    function initCategory(el) {
        var $eventList = $(el).parent().prev();
        var more = $eventList.find('.arlo-events-item').length > 3
        if(more) {
            $eventList.find('.arlo-events-item:last').remove();
        } else {
            $(el).parent().hide();
        }
        $eventList.find('.arlo-events-item').removeClass('hide')
    }

    function initSchedule(el) {
        var $eventList = $(el).parent().prev();
        var more = $eventList.find('.arlo-schedules-item').length > 3
        if (more) {
            $eventList.find('.arlo-schedules-item:last').remove();
        } else {
            $(el).parent().hide();
        }
        $eventList.find('.arlo-schedules-item').removeClass('hide')
    }
    $(".arlo-btn-more-category").click(function() {
        $(this).attr('disabled', 'disabled')
        var that = this
        if(hasNextPage()) {
            $.get(getNextPageLink(), function(html) {
                var $categoriesHtml = $($(html).find('.arlo-catalog-category-wrppaer').html());
                $('.arlo-catalog-category-wrppaer').append($categoriesHtml);
                var pagerHtml = $(html).find('.arlo-pager').html()
                $(".arlo-pager").html(pagerHtml);
                $(that).removeAttr('disabled');
                var $moreEvents = $categoriesHtml.find('.arlo-btn-more-event');
                $moreEvents.each(function() {
                    initFn($(this))
                })
                if(!hasNextPage()) {
                    $(that).parent().hide(); //hide load more ,if there is no next link
                }
            })
        }
    })

    var eventsSelector = '' 
    var initFn = null
    var pageScriptDatas = [
        {
            pageSelector: '.arlo-catelog-page',
            eventsSelector: '.arlo-events .arlo-events-item',
            fn: initCategory
        },
        {
            pageSelector: '.arlo-schedule-page',
            eventsSelector: '.arlo-schedules .arlo-schedules-item',
            fn: initSchedule
        },
    ]

    for (var d of pageScriptDatas) {
        if ($(d.pageSelector).length > 0) {
            initFn = d.fn
            eventsSelector = d.eventsSelector
            break;
        }
    }

    $('.arlo-btn-more-event').each(function () {
        initFn($(this))
    })

    
    
    function venueListDataFunction() {
        initListPageFunc('arlo-venue-list', $('.arlo-button-morevenue'))
    }
    function presenterListDataFunction() {
        initListPageFunc('arlo-presenters', $(".arlo-more-presenter"))
    }
    function oaListDataFunction() {
        initListPageFunc('arlo-activities', $('.arlo-more-oa'))
    }
    function eventSearchDataFunction() {
        initListPageFunc('arlo-event-search-result', $(".arlo-button-event-search-more"))
    }
    function eventDataFunction() {
        arloInitDiscountIcon();
        //initListPageFunc('arlo-event-list-items', $(".arlo-event-more"), arloInitDiscountIcon)
        var eventPage = 1;
        var eventSize = 4;
        function page() {
            var idx = 0;
            var visiable = 0;
            $(".arlo-event-list-items-item").each(function() {
                if(idx < eventPage * eventSize) {
                    $(this).show();
                    visiable+=1
                } else {
                    $(this).hide();
                }
                idx+=1
            })
            if(visiable >= $(".arlo-event-list-items-item").length) {
                $(".arlo-event-more").hide();
            }
        }
        $(".arlo-event-more").click(function() {
            eventPage += 1;
            page();
        })
        page()
    }
    function upcomingDataFunction() {
        initListPageFunc('arlo-upcoming-result-events', $(".arlo-btn-more-uce"),function($list) {
            $list.find('.arlo-sessions-popup-trigger').each(function(i, el) {
                var modal = new tingle.modal({
                    footer: true,
                    cssClass: ['arlo'],
                    closeMethods: ['overlay', 'button', 'escape']
                });
    
                var popupContent = $($(el).data('target')).html();
    
                modal.setContent(popupContent);
    
                modal.addFooterBtn('Close', 'arlo-close-btn button btn btn-primary', function() {
                    modal.close();
                });
    
                $(el).click(function(e) {
                    e.preventDefault();
                    modal.open()
                });
            });
            arloAppenCloseIconToModal()
        })
    }
    const pageFuncConfig = {
        '.arlo-event-search' : eventSearchDataFunction,
        '.arlo-event-page' : eventDataFunction,
        '.arlo-presenters' :  presenterListDataFunction,
        '.arlo-upcoming'  : upcomingDataFunction,
        '.arlo-venue-list': venueListDataFunction,
        '.arlo-activities': oaListDataFunction,
    }
    for(var cls in pageFuncConfig) {
        if($(cls).length > 0) pageFuncConfig[cls]();
    }
});
