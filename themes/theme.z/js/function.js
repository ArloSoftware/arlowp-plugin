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