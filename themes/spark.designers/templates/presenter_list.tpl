<div class="arlo" id="arlo">

    <ul class="arlo-list presenters">
        [arlo_presenter_list_item]
            <li class='arlo-cf'>
                <div class="arlo-presenters-list-header">
                    <h2 class="arlo-presenter-name">[arlo_presenter_permalink wrap='<i class="icons8-gender-neutral-user"></i><a href="%s">'][arlo_presenter_name]</a></h2>
                    [arlo_presenter_permalink wrap='<div class="arlo-presenter-info-link"><a href="%s">']View all presenter information</a></div>
                </div>

                [arlo_presenter_profile wrap='<div class="arlo-presenter-content">%s</div>']
                [arlo_presenter_qualifications wrap='<div class="arlo-presenter-content"><h4>Qualifications</h4>%s</div>']
                [arlo_presenter_social_link wrap='<div class="arlo-presenter-content"><h4>Social Network</h4>%s</div>']
                [arlo_presenter_interests wrap='<div class="arlo-presenter-content"><h4>Interests</h4>%s</div>']
            </li>
            [arlo_presenter_rich_snippet]
        [/arlo_presenter_list_item]
    </ul>

    <div class="arlo-pagination">
        [arlo_presenter_list_pagination]
    </div>
    [arlo_powered_by]
    
</div>
