
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
    function frontEndShowMore($domeList, pageNum, pageSize) {
        var idx = 0;
        var lastIdx = pageSize * pageNum - 1;
        $domeList.each(function() {
            if(idx <= lastIdx) {
                $(this).removeClass('hide')
            }
            idx++;
        })
        return $domeList.length <= lastIdx + 1 //all data loaded
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
        var slug = $(this).data('slug')
        
        var filters = {
            'cat': 'arlo-filter-category',
            'location': 'arlo-filter-location',
            'delivery': 'arlo-filter-delivery',
        };
        var page = $('#arlo-page').val();
        if (page[page.length-1] != '/') {
            page = page + '/';
        }
        if ($('#arlo-filter-region').length > 0) {
            page += 'region-' + $('#arlo-filter-region').val() + '/';
        }
        var url = page;
        var urlParams = ['pagefor=event', 'epage=' + (currentPage + 1)];

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
            var $eventItems =$(html).find('.arlo-events .arlo-events-item');
            var $eventList = $button.parent().prev();
            $eventItems.each(function() {
                $(this).removeClass('hide')
                $eventList.append($(this));
            })
            if($eventItems.length <= 3) {
                $button.parent().hide(); //hide load more ,if there is no next link
            }
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
                initCategory($categoriesHtml.find('.arlo-btn-more-event'))
                if(!hasNextPage()) {
                    $(that).parent().hide(); //hide load more ,if there is no next link
                }
            })
        }
    })
    
    $('.arlo-btn-more-event').each(function() {
        initCategory($(this))
    })
 });