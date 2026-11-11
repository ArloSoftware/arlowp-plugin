# SHORTCODES

## Placeholders in attribute values

Some attributes take your own markup and insert a value into it. In these attributes `%s` stands for the value and `%%` is a literal percent sign. Any other percent sign is left as written, so `style="width:100%"` is safe. Nothing else is read as a format: `%d` is not a placeholder except in `counts`, and positional forms such as `%1$s` render as written.

* `wrap` (any Arlo shortcode): `%s` is the shortcode output. Example: `[arlo_event_name wrap="<h2 style='width:100%'>%s</h2>"]`
* `group_header` (`[arlo_event_template_list_item group="category"]`): `%s` is the category name.
* `events_after` (`[arlo_event_template_list_item group="category"]`): `%s` is the category slug. Each category list is fetched with one event more than `limit`, and the shortcode ends the list with a hidden `<span class="arlo-events-limit" data-limit="n"></span>` (placed just before `events_after`) that gives a load-more script the `limit`. It is output only when `events_after` is set.
* `title` (`[arlo_categories]`): `%s` is the category name. The `%s` is removed when more than one category is selected.
* `item` (`[arlo_category_breadcrumb]`, `[arlo_event_category_path]`): `{slug}` is the category slug and `{label}` is the category name. They are not `%s` placeholders.
* The markup from `item`, `title` (`[arlo_categories]`), `group_header` and `events_after` is sanitised again after the value is inserted, so a value placed in `href`, `src` or `style` cannot run script. Put these values in element text or a `data-` attribute, or after a fixed prefix such as `href="/catalogue/{slug}"`.
* `counts` (`[arlo_categories]`): `%s` or `%d` is the number of event templates in the category.
* `format` (`[arlo_event_start_date]`, `[arlo_event_end_date]`, `[arlo_event_next_running]`, and `startdateformat` / `enddateformat`): a strftime format, or a PHP `date()` format when it contains no `%`.
  * Supported conversions: `%a %A %b %B %h %c %C %d %D %e %F %g %G %H %I %j %k %l %m %M %n %p %P %r %R %s %S %t %T %u %U %V %w %W %x %X %y %Y %z %Z` and `%%`.
  * Flags and width: `%-d` (no padding), `%_d` (pad with spaces), `%0e` (pad with zeros), `%^a` (upper case) and a width such as `%5d`.
  * Differences from the C library: names are always English; `%e`, `%k` and `%l` are not padded unless `_` or a width is given; `%G` and `%g` give the calendar year, not the ISO week-numbering year that the C library gives (a compatibility shim for saved copies of the older Starter templates, which used `%G` as the year; use `%Y` and `%y` in your own templates); `%Z` is the event's time zone abbreviation and `%z` is its offset with a colon (`+13:00`), where the C library prints `+1300`.
  * Anything else, including the `#` flag, `%E` and `%O` modifiers, unknown letters and a width above 64, is shown as written, as is a `%` that does not start a conversion.
* `aftertext` (`[arlo_event_next_running]`) is appended as written and has no placeholder.
---

## Event Template

* [arlo_event_template_list]
* [arlo_event_template_list_item][/arlo_event_template_list_item]
* [arlo_event_template_list_pagination]
* [arlo_event_template_code]
* [arlo_event_template_name]
* [arlo_event_template_summary]
* [arlo_event_template_permalink]
* [arlo_timezones]
* [arlo_suggest_templates limit="5" base="category" tagprefix="group_" onlyscheduled="false" regionalized="false"][/arlo_suggest_templates]
* [arlo_suggest_datelocation]
---

## Event Template Content Field

* [arlo_content_field_list_item][/arlo_content_field_list_item]
* [arlo_content_field_name]
* [arlo_content_field_text]
---

## Event

* [arlo_event_list][/arlo_event_list]
* [arlo_event_list_item][/arlo_event_list_item]
* [arlo_event_name]
* [arlo_event_code]
* [arlo_event_duration]
* [arlo_event_price]
* [arlo_event_next_running ignore_resiter_link="false"]
  > **Note**: The attribute name `ignore_resiter_link` is a known misspelling of
  > `ignore_register_link`. It has been retained as-is to avoid breaking existing
  > site configurations. Do not rename it.
* [arlo_event_start_date format=""]
* [arlo_event_end_date format=""]
* [arlo_event_location]
* [arlo_event_provider]
* [arlo_event_delivery]
* [arlo_event_session_description]
* [arlo_event_summary]
* [arlo_event_presenters layout='list||string' link='permalink||yes||viewuri||false||{url}']
* [arlo_event_notice]
* [arlo_event_offers]
* [arlo_event_offers_hasdiscount]
* [arlo_event_registration]
* [arlo_event_isfull]
* [arlo_event_notice]
* [arlo_event_tags]
* [arlo_event_credits]
* [arlo_event_rich_snippet]
* [arlo_event_session_list_item][/arlo_event_session_list_item]
---

## Upcoming

* [arlo_upcoming_list]
* [arlo_upcoming_list_item][/arlo_upcoming_list_item]
* [arlo_upcoming_list_pagination]
* [arlo_event_notice]
* [arlo_upcoming_offer]

## Presenter

* [arlo_presenter_list]
* [arlo_presenter_list_item][/arlo_presenter_list_item]
* [arlo_presenter_list_pagination]
* [arlo_presenter_name]
* [arlo_presenter_permalink]
* [arlo_presenter_link]
* [arlo_presenter_profile]
* [arlo_presenter_qualifications]
* [arlo_presenter_social network='' linktext='']
* [arlo_presenter_events_list]
---

## Venue

* [arlo_venue_list]
* [arlo_venue_list_item][/arlo_venue_list_item]
* [arlo_venue_list_pagination]
* [arlo_venue_name]
* [arlo_venue_map height="" width="" zoom=""]
* [arlo_venue_address items='line1,line2,line3,line4,suburb,city,state,post_code,country' layout='string||list']
* [arlo_venue_permalink]
* [arlo_venue_link]
* [arlo_venue_directions]
* [arlo_venue_parking]
---

## Credit

* [arlo_powered_by]
  > **Note**: Renders a small "Powered by Arlo" logo linking to arlo.co only when
  > the **Powered by Arlo link enabled** checkbox is ticked in Arlo > Settings > Misc.
  > This enables the credit on Arlo pages and widgets whose templates contain the shortcode;
  > it does not add the shortcode to templates.
  > It renders nothing by default.