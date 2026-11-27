# SHORTCODES

## Pagination limits

The list pagination shortcodes use a positive integer `limit`, normally matching the list-item shortcode's limit. If omitted, it defaults to WordPress's `posts_per_page`. If the resolved limit converts to zero or is negative, pagination renders nothing, even with an empty dataset. This includes `[arlo_presenter_list_pagination limit="0"]` and `limit="invalid"`. A limit of `-1` also omits pagination; it does not change how the list retrieves its items or enable unlimited lists.

Existing caller defaults are unchanged: for example, a nonnumeric `limit` directly on `[arlo_upcoming_list_pagination]` uses the WordPress default. Omitting pagination does not write to the Arlo log.

## Encoded quotes in template shortcodes

`decode_quotes_in_shortcodes="true"` (any Arlo shortcode) turns `&quot;` and `&apos;` back into quotes inside square brackets in the template content between the shortcode's tags, so a shortcode saved with encoded quotes, such as one from another plugin, still runs. Text outside square brackets is left as written, and the values Arlo renders from imported data are never decoded. It defaults to `false`.

## Placeholders in attribute values

Some attributes take your own markup and insert a value into it. In these attributes `%s` stands for the value and `%%` is a literal percent sign. Any other percent sign is left as written, so `style="width:100%"` is safe. Nothing else is read as a format: `%d` is not a placeholder except in `counts`, and positional forms such as `%1$s` render as written.

* `wrap` (any Arlo shortcode): WordPress sanitises the wrapper before substitution. Only the `wrap` argument is inspected for `%s`, not the rest of the template. Where `%s` appears determines what can replace it:
  * **Plain text or element content:** `wrap="Course: %s"` and `wrap="<div>%s</div>"` both insert the normal rendered output, including any `label` or `strip_html` processing. If the shortcode renders `<em>Course</em>`, that markup is preserved in either case rather than escaped into visible angle brackets.
  * **Link destination:** Use `%s` as the **whole** `href` value with `arlo_event_template_permalink`, `arlo_event_template_link`, `arlo_presenter_link`, `arlo_presenter_permalink`, `arlo_presenter_social_link` (without `linktext`, which returns a link element rather than a URL), `arlo_venue_direction`, `arlo_venue_events_link`, `arlo_venue_link`, `arlo_venue_permalink`, or `arlo_event_template_list_image_src`. These accept HTTP(S) or relative URLs, including query-only and fragment-only links. The URL is escaped with `esc_url()` and the final attribute value is checked again: `/courses/?a=1&b=2` keeps both query parameters even though the `&` is escaped in the HTML. Filters can return a raw URL or one escaped by WordPress for display; return raw URLs when possible. An opening tag is valid: `[arlo_event_template_permalink wrap='<a href="%s">'][arlo_event_template_name]</a>`. Use `arlo_venue_direction` (singular) for a map link and `arlo_venue_directions` (plural) in element content for formatted directions.
  * **Image URL:** `arlo_event_template_list_image_src` can also fill a whole `src` (`wrap='<img src="%s" alt="">'`) or a `url()` in a `style` attribute (`wrap="<div style='background-image:url(%s)'></div>"`). The URL is validated like a link; parentheses and apostrophes in it are percent-encoded so it stays inside the `url()`. Every `%s` in the `style` value must be inside a `url()`. Write the `url()` without quotes, as in the example: WordPress 7.0's own sanitising removes a quoted `url()` from a `style` attribute before the wrapper is read, and later versions keep it.
  * **Class list:** Use `arlo_event_template_tags` or `arlo_event_tags` with `layout="class"` and place `%s` as a separate token, for example `wrap='<div class="delivery %s">Delivery</div>'`. A WordPress attribute filter can select or override that layout; the class slot uses the layout the tag handler actually resolved. If the shortcode supplies `arlo-online arlo-evening`, the result is `class="delivery arlo-online arlo-evening"`. The tag-derived tokens are checked and the combined class list is escaped with `esc_attr()`. `arlo_event_code`, `arlo_event_template_code` and `arlo_oa_code` can also fill a class token (`wrap='<li class="arlo-cf %s">'`): the code is split on whitespace and each part is reduced to letters, digits, hyphens and underscores. A class slot that cannot be filled (another shortcode, a non-class layout, a value that is not valid class tokens, or a token such as `tag-%s` that contains more than the slot) is dropped on its own and the static class tokens are kept.
  * **Unsupported or ambiguous placement:** Attribute slots require a scalar filtered value and do not apply `label`, `strip_html`, or nested shortcode expansion. Objects with `__toString()` can still supply body output. An unsupported attribute slot or an invalid value removes just that attribute (a class slot only loses the slot itself, as described above): `[arlo_label wrap='<a href="%s">Link</a>']<em>Course</em>[/arlo_label]` keeps `Link` but drops `href`. A placeholder in a tag name, attribute name, comment (such as `<!-- %s -->`), a repeated attribute that the browser ignores, inside or after a `noscript` or `plaintext` element, or other ambiguous location skips the whole wrapper and renders the shortcode normally. Empty shortcode results are not wrapped. A WordPress attribute filter can suppress output by setting `wrap` to `null` or an empty string.
  * Wrapper fallbacks are not logged by default and do not write to the Arlo log table. For tests or local diagnostics, PHP code can call `\ArloTraining\Shortcodes\ShortcodeWrapSanitizer::set_logging_handler( $handler )` with a callback that accepts one diagnostic string. The message contains no template or shortcode content. Pass `null` to clear the handler for the current request.
* `group_header` (`[arlo_event_template_list_item group="category"]`): `%s` is the category name.
* `events_after` (`[arlo_event_template_list_item group="category"]`): `%s` is the category slug. Each category list is fetched with one event more than `limit`, and the shortcode ends the list with a hidden `<span class="arlo-events-limit" data-limit="n" data-slug="slug"></span>` (placed just before `events_after`) that gives a load-more script the `limit` and the category slug of the list. The `data-slug` is left out when the slug has characters other than letters, digits, hyphens, underscores and percent signs. It is output only when `events_after` is set.
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