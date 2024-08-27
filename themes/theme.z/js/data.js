
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
    //$(".arlo-btn-more-event").trigger('click');
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
