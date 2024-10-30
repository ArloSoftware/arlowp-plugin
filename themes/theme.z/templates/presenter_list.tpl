<div id="arloapp" class="arlo-desktop">
<div class="arlo-wrapper">
	<div class=" arlo-presenters" role="list">
		[arlo_presenter_list_item limit="8"]
			<div class="arlo-presenters-item" role="listitem">
				<div class="arlo-presenters-item-inner">
					[arlo_presenter_profile_avatar placeholder="themes/theme.z/images/noimage.jpg"]
				</div>
				<div class="arlo-presenters-item-footer">
					<p role="heading">[arlo_presenter_name]</p>
					[arlo_presenter_permalink wrap='<a href="%s" role="button" class="arlo-button">']Learn more</a>

				</div>
			</div>
		[/arlo_presenter_list_item]
	</div>
	<div class="arlo-align-center-row">
		<button class="arlo-button arlo-gray arlo-more-presenter">Show more</button>
	</div>
	<div class="arlo-pager">
		[arlo_presenter_list_pagination limit="8"]
	</div>
</div>
</div>