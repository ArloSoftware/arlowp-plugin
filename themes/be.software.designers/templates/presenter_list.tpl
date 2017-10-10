<div class="arlo" id="arlo">

    <ul class="arlo-list presenters">
        [arlo_presenter_list_item]
            <li>
                <h2>[arlo_presenter_permalink wrap="<a href='%s'>"][arlo_presenter_name]</a></h2>
                [arlo_presenter_permalink wrap="<a href='%s' class='arlo-presenter-info-link'>"]View all presenter information</a>

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
