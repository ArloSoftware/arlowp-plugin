[arlo_categories title="<h5>%s Categories</h5>" wrap='<div class="arlo-categories">%s</div>']
[arlo_template_region_selector]
[arlo_schedule_filters]
[arlo_category_header]

<table class="table event-templates">
	<tr>
		<th>Name</th>
		<th>Duration</th>
		<th>Price 
			<small>(excl. GST)</small>
		</th>
		<th>Next Running</th>
	</tr>
    [arlo_event_template_list_item group="category" limit="20"]
        [arlo_group_divider wrap='<tr class="arlo-group-divider"><th colspan="4">%s</th></tr>']
        <tr>
            <td>
                [arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a>
            </td>
            <td>[arlo_event_template_advertised_duration]</td>
            <td>[arlo_event_price showfrom="false"]</td>
            <td>[arlo_event_next_running]</td>
        </tr>
      [arlo_event_template_rich_snippet]
    [/arlo_event_template_list_item]
</table>

[arlo_category_footer]
[arlo_no_event_text]
[arlo_schedule_pagination limit="20"]
[arlo_powered_by]
