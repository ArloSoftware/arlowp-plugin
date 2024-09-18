<div id="arloapp" class="arlo-desktop">
    <div class="arlo-wrapper arlo-event-search">
        <form class="arlo-event-search-form" role="search">
            <h1>Search courses</h1>
            <div class="arlo-event-search-form-fields">
                [arlo_search_field placeholder="Keywords search" buttonclass="arlo-button" showbutton="true" buttontext='Search']
            </div>
        </form>
        <div class="arlo-event-search-result" role="list">

            [arlo_event_template_list_item limit="10"]
                <div class="arlo-event-search-result-item" role="listitem">
                    <div class="arlo-event-search-result-item-nav">
                        [arlo_event_category_path item='<a data-slug="{slug}">{label}</a>']
                    </div>
                    <p>[arlo_event_template_permalink wrap='<a href="%s" class="arlo-event-search-result-item-title">'][arlo_event_template_name]</a></p>
                    <p class="arlo-event-search-result-item-desc">[arlo_event_template_summary]</p>
                    [arlo_event_next_running text="{%date%}<br />{%location%}<span class='arlo-discount'>Discount</span>" buttonclass="arlo-event-search-result-item-schedule-item" layout="list" registerclass="arlo-single-register" limit="100" format="d M Y" removeyear="0" aftertext="" template_link="permalink"]
                </div>
            [arlo_event_template_rich_snippet]
            [/arlo_event_template_list_item]
            
        </div>
        <div class="arlo-align-center-row">
            <button class="arlo-button arlo-button-event-search-more arlo-gray">Show more</button>
        </div>
        [arlo_no_event_text before='<div class="arlo-not-found">' after='<a href="">Enquire about a course you are interested in.</a></div>']
        [arlo_powered_by]
    </div>
    
    <div class="arlo-pager">
        [arlo_event_template_list_pagination limit="10" wrap='<div class="arlo-pagination">%s</div>']
    </div>
    
</div>


