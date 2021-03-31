jQuery(function($){ 

    var setNumberOfEventColumns = function (container) {
        // set number of event columns based on the container width
        var $container = $(container);
        var containerWidth = $container.width();

        $container.removeClass('arlo-xs arlo-sm arlo-md');

        if (containerWidth < 1100) {
            $container.addClass('arlo-md');
        }

        if (containerWidth < 992) {
            $container.addClass('arlo-sm');
        }

        if (containerWidth < 767) {
            $container.addClass('arlo-xs');
        }
    }

    $(window).resize(function() {
        setNumberOfEventColumns('.arlo#arlo');
    });


    var setNumberOfEvents = function() {
        if ($('.arlo_event-template-default').length !== 0) {
            $('.arlo-template-events-list').removeClass('events-1 events-2 events-3 events-4');

            var eventsCount = $('.arlo-template-events-list .event').length;

            switch (eventsCount) {
                case 1:
                    $('.arlo-template-events-list').addClass('events-1');
                    break;
                case 2:
                    $('.arlo-template-events-list').addClass('events-2');
                    break;
                case 3:
                    $('.arlo-template-events-list').addClass('events-3');
                    break;
                default:
                    $('.arlo-template-events-list').addClass('events-4');
                    break;
            }

        }
    }

    var setDiscountAndLimited = function (container) {
        // set the corner labels on Schedule page for Discount and Limited places
        var $container = $(container);

        $container.find('.arlo-event-discount:not(.arlo-event-full)').append('<div class="corner-label discount"></div>');
        $container.find('.arlo-event-limited:not(.arlo-event-full)').append('<div class="corner-label places-limited"></div>');
    }


    $(document).ready(function() {
        'use strict';

        setNumberOfEventColumns('.arlo#arlo');
        setNumberOfEvents();

        setDiscountAndLimited('.arlo#arlo .scheduled-dates');


        // Animate in items
        function animateIn(className) {
            var elementsInViewport = $(className).filter( function(index) {
                return isElementInViewport(this);
            });

            if ( elementsInViewport.length > 0 ) {
                setTimeout(function() {
                    $(className).addClass('is-in-viewport');
                }, 200);
            }
        }

        function animateInItems() {
            var classes = ['.link-item','.schedule-date','.event-card', '.scheduled-dates a'];

            $.each(classes, function(i, className){
                animateIn(className);
            });
        }

        $(window).scroll(animateInItems);

        animateInItems();

        /*if (!isTouchEnabled()) {
            $('.popover-trigger').click(function() {
                $('.popover-trigger').each(function() {
                    $(this).popover('hide');
                }); 
            });

            $('.popover-trigger').popover({ 
                trigger: "hover",
                placement: 'top',
                html: true,
                content: function() {
                    return $(this).find(".popover-content").html();
                },
                title: function() {
                    return $(this).find(".popover-title").html();
                }
            });
        }*/

        function calculateEventHeights() {
            if ($('.arlo#arlo').width() >= 768) {
                var highest = 0;
                $('.arlo#arlo .event').height('auto');
                $('.arlo#arlo .event .event-container').css('min-height', '0');

                $('.arlo#arlo .event:not(.show-more-hidden), .arlo#arlo .event.show-more-visible').each(function(i,event) {
                    if ($(event).height() > highest) {
                        highest = $(event).height();
                    }
                });

                $('.arlo#arlo .event').height(highest);
                $('.arlo#arlo .event .event-container').css('min-height', highest);
            }
        }

        calculateEventHeights();

        $('.arlo-show-more-link').click(function() {
            setTimeout(calculateEventHeights,0);
        });

        function attachExpandEvents() {

            // collapsable elements
            if ( !$('.arlo#arlo').hasClass('arlo-xs') ) {
                $('.arlo#arlo .event .event-container').off('click');
                $('.arlo#arlo .event .event-container').off('focusout');

                 $('.arlo#arlo .event .event-container').on({
                  focusout: function(e) {
                    $(this).closest('.event').removeClass('expanded');
                  },
                  click: function(e) {                    
                    var eventItem = $(this).closest('.event');
                    if ( eventItem.hasClass('expanded') ) {
                        setTimeout(function() {
                            eventItem.find('.event-container').focusout();
                        }, 0);
                    } else {
                        eventItem.addClass('expanded');
                    }
                  }
                });
            } else {
                // Expand mobile
                $('.arlo#arlo .event-content, .arlo#arlo .online-activity .btn-expand.mobile, .arlo#arlo .event .btn-expand.mobile, .arlo#arlo .template-details, .arlo#arlo .schedule-item').click(function(event) {
                    expandMobile(event,this);
                });
            }

            if ($('.arlo#arlo').width() > 768 && $('.arlo#arlo').width() < 992) {
                $('.arlo#arlo .schedule-item').click(function(event) {
                    expandMobile(event,this);
                });
            }
        }

        attachExpandEvents();


        function expandMobile(event,_this) {
            if (!$(event.target).is('a:not(.btn-expand), button')) {
                if ($(event.target).parent().is('a:not(.btn-expand)')) { return; };
                if ($(event.target).closest('.schedule-date').length > 0) { return; };
                event.preventDefault();
                var eventItem = $(_this).closest('.event, .catalogue-item, .online-activity, .schedule-item');

                eventItem.toggleClass('expanded');
            }
        }

        // Event card
        $('.arlo#arlo .event-card').hover(function() {
            $(this).addClass('hover');
        });

        $('.arlo#arlo .event-card').mouseleave(function() {
            $(this).removeClass('hover');
        });

        $('.arlo#arlo .event-card').on('touchstart',function() {
            $(this).toggleClass('hover');
        });


        $('.arlo#arlo .similar-course').click(function(event) {
            event.preventDefault();
            $(this).toggleClass('expanded');
        });

        $('.event-card .btn-expand').click(function() {
            $(this).closest('.event-card').toggleClass('expanded');
            $(this).closest('.event-card').find('.event-expandable').collapse('toggle');
        });


        // Sessions popups
        $('.arlo#arlo .show-sessions-trigger').click(function(event) {
            event.preventDefault();
            $(this).closest('.event').find('#sessions').modal();
        });


        if (isTouchEnabled() && typeof jQuery().slick === "function") {
            $('.arlo#arlo .scheduled-dates, .arlo#arlo .search-scheduled-dates').slick({
                prevArrow: false,
                nextArrow: false,
                infinite: false,
                swipeToSlide: true,
                variableWidth: true
            });
        }



        function isTouchEnabled() {
            return ('ontouchstart' in window || navigator.maxTouchPoints);
        }


        /**  emulates behaviour to be implemented later **/

        $('.arlo#arlo .show-more-btn').click(function(event) {
            event.preventDefault();
            $('.arlo#arlo .show-more-hidden').addClass('show-more-visible');
            $('.arlo#arlo .show-more-wrapper').attr('style','display: none !important');
            calculateEventHeights();
        });

        // Fix for if the category list el does not exist
        $("#arlo.arlo .filter-toggle[data-toggle='collapse']").click(function () {
            var $this = $(this);
            if ($($this.attr('data-target')).length == 0 && !$this.hasClass('collapsed')) {
                window.setTimeout(function () {
                    $this.addClass('collapsed');
                }, 100);
            }
        });

    });

    function isElementInViewport(el) {
        if (typeof jQuery === "function" && el instanceof jQuery) {
            el = el[0];
        }
        if (typeof el == 'undefined') {
            return;
        }

        if (typeof el.getBoundingClientRect !== 'undefined') {
            var rect = el.getBoundingClientRect();

            var isInViewport = (
                (rect.top >= 0 && rect.bottom > 0) &&
                (rect.left >= 0 && rect.right > 0) &&
                rect.bottom <= $(window).height() &&
                rect.right <= $(window).width()
            );

            return isInViewport;
        }
    }

 });