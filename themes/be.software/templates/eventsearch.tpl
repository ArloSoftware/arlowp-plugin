[arlo_template_search_region_selector]

<table class="table event-templates">
	<tr>
		<th>Name</th>
		<th>Duration</th>
		<th>
            Price <small>(excl. GST)</small>
		</th>
		<th>Next Running</th>
	</tr>
    [arlo_event_template_list_item]
        <tr>
            <td>
                [arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_code] - [arlo_event_template_name]</a>
            </td>
            <td>[arlo_event_duration]</td>
            <td>[arlo_event_price]</td>
            <td>[arlo_event_next_running]</td>
        </tr>
    [/arlo_event_template_list_item]
</table>

[arlo_event_template_list_pagination]
[arlo_no_event_text]