// Theme Z: the "Show more" buttons of the catalogue, schedule, event page and list templates.
//
// Relies on jQuery and on globals from other scripts: tingle (modal library), arloInitDiscountIcon and
// arloAppenCloseIconToModal (function.js). The class names below, misspellings included, are the ones the templates
// and shortcodes render, so they must be changed in the templates first.
jQuery(function($) {
    // The lists that page through a pager's next link, each with its Show more button. The class names are the ones
    // the templates render.
    const PAGED_LISTS = {
        categories: { listClass: 'arlo-catalog-category-wrppaer', buttonSelector: '.arlo-btn-more-category' },
        search: { listClass: 'arlo-event-search-result', buttonSelector: '.arlo-button-event-search-more' },
        presenters: { listClass: 'arlo-presenters', buttonSelector: '.arlo-more-presenter' },
        upcoming: { listClass: 'arlo-upcoming-result-events', buttonSelector: '.arlo-btn-more-uce' },
        venues: { listClass: 'arlo-venue-list', buttonSelector: '.arlo-button-morevenue' },
        activities: { listClass: 'arlo-activities', buttonSelector: '.arlo-more-oa' },
    };
    const ANY_PAGED_LIST = Object.values(PAGED_LISTS).map((config) => '.' + config.listClass).join(', ');

    // A list together with the pager and Show more buttons that belong to it. Searching outward from the list, the
    // first ancestor that holds both a pager and a button is the list's own template; the search never enters an
    // ancestor that holds another paged list, so a page with several lists, or a list without a pager, never lends
    // one list's pager to another. When that template holds several paged lists, a list owns the controls between it
    // and the next list. If that range has no button of its own, the lists share one set of controls that follows them,
    // and the first of them owns it. The pager's markup differs between templates, so only the classes are relied on.
    class ListScope {
        constructor($list, listClass, buttonSelector) {
            let $scope = $list.parent();
            for (;;) {
                if ($scope.find('.arlo-pager').length > 0 && $scope.find(buttonSelector).length > 0) break;
                const $outer = $scope.parent();
                if ($outer.length === 0 || $outer.find(ANY_PAGED_LIST).length > 1) break;
                $scope = $outer;
            }
            this.$list = $list;
            Object.assign(this, ListScope.controls($scope, $list, listClass, buttonSelector));
        }

        static controls($scope, $list, listClass, buttonSelector) {
            const $pagers = $scope.find('.arlo-pager');
            const $buttons = $scope.find(buttonSelector);
            const lists = $scope.find(ANY_PAGED_LIST).toArray();
            if (lists.length <= 1) return { $pagers, $buttons };
            const follows = (from, to) => (from.compareDocumentPosition(to) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0;
            const listsAfter = lists.filter((other) => follows($list[0], other));
            const within = ($set, end) => $set.filter((i, el) => follows($list[0], el) && (!end || follows(el, end)));
            let end = listsAfter[0];
            if (within($buttons, end).length === 0) {
                // The lists up to the first button after this one share it, so the controls end at the next list after
                // that button. A list of another kind in between means the button belongs to that list.
                const shared = $buttons.toArray().find((button) => follows($list[0], button));
                const blocked = shared && listsAfter.some((other) => follows(other, shared) && !$(other).hasClass(listClass));
                end = shared && !blocked ? listsAfter.find((other) => follows(shared, other)) : $list[0];
            }
            return { $pagers: within($pagers, end), $buttons: within($buttons, end) };
        }

        nextLink() {
            const href = this.$pagers.find('.next.page-numbers').first().attr('href');
            return href ? href.trim() : '';
        }

        // The pagers become the ones of the same position in the scope of the list on the fetched page.
        replacePagersFrom(other) {
            this.$pagers.each((i, pagerElement) => {
                $(pagerElement).empty().append(other.$pagers.eq(i).children().clone());
            });
        }

        // A button's own row is hidden with it. When the row also holds a list or a pager, as in a container that
        // has its controls as direct children, only the button is hidden.
        hideButtons() {
            this.$buttons.each((i, button) => {
                const $parent = $(button).parent();
                ($parent.find(ANY_PAGED_LIST + ', .arlo-pager').length > 0 ? $(button) : $parent).hide();
            });
        }

        setBusy(busy) {
            this.$buttons.prop('disabled', busy);
        }
    }

    // A list that pages through its pager's next link: venues, presenters, online activities, event search, upcoming and
    // the categories of the catalogue and schedule. Every list on the page is paged on its own: the next page's items are
    // appended to that list and only its pager is replaced. The next page is the whole page, so the list's counterpart
    // there is the list of the same position.
    class PagedList {
        constructor({ listClass, buttonSelector, onAppend }) {
            this.listClass = listClass;
            this.buttonSelector = buttonSelector;
            this.onAppend = onAppend;
        }

        start() {
            // A template can have the component's marker without the list itself.
            const count = $('.' + this.listClass).length;
            $('.' + this.listClass).each((position, list) => {
                const scope = new ListScope($(list), this.listClass, this.buttonSelector);
                if (scope.$buttons.length === 0) return;
                // Lists that share one button, because a template puts them in a single container, cannot be told
                // apart. The first list owns the button; a second would fire a duplicate request on every click.
                if (scope.$buttons.is((i, button) => $(button).data('arloPagedList'))) return;
                scope.$buttons.data('arloPagedList', true);
                if (!scope.nextLink()) {
                    scope.hideButtons();
                }
                let loading = false;
                scope.$buttons.click(() => {
                    if (loading) return;
                    // No next link means there is nothing to request; the button is not needed.
                    if (!scope.nextLink()) {
                        scope.hideButtons();
                        return;
                    }
                    loading = true;
                    scope.setBusy(true);
                    // A failed request, or a page without the list, keeps the items and the pager so the visitor can retry.
                    const release = () => {
                        loading = false;
                        scope.setBusy(false);
                    };
                    $.get(scope.nextLink(), (html) => {
                        const next = this.listOf(html, position, count);
                        if (!next) {
                            release();
                            return;
                        }
                        const $items = next.$list.children().clone();
                        scope.replacePagersFrom(next);
                        scope.$list.append($items);
                        if (!scope.nextLink()) {
                            scope.hideButtons(); // no next link: nothing more to load
                        }
                        release();
                        if (this.onAppend) this.onAppend($items);
                    }).fail(release);
                });
            });
        }

        // The scope of the list at the position on the fetched page. Positions only identify a list when the page has
        // as many lists of this kind as this one, so a page with fewer or more has no list that can be trusted.
        listOf(html, position, count) {
            if (typeof html !== 'string') return null;
            const $page = $('<div></div>').append($.parseHTML(html, document, true));
            const $lists = $page.find('.' + this.listClass);
            const $list = $lists.length === count ? $lists.eq(position) : $();
            return $list.length > 0 ? new ListScope($list, this.listClass, this.buttonSelector) : null;
        }
    }

    // Catalogue and Schedule: a list of courses for each category, each with its own Show more button, and a Show more
    // categories button below them. The two templates differ only in their page and item classes. A page has one or the other.
    const GROUPED_LIST_KINDS = [
        { pageSelector: '.arlo-catelog-page', itemSelector: '.arlo-events-item' },
        { pageSelector: '.arlo-schedule-page', itemSelector: '.arlo-schedules-item' },
    ];

    class GroupedList {
        // The first kind whose page is present.
        static detect() {
            const kind = GROUPED_LIST_KINDS.find((candidate) => $(candidate.pageSelector).length > 0);
            return kind ? new GroupedList(kind) : null;
        }

        constructor({ itemSelector }) {
            this.itemSelector = itemSelector;
        }

        start() {
            this.bindShowMoreCourses();
            this.bindShowMoreCategories();
            $('.arlo-btn-more-event').each((i, button) => this.initCategoryButton(button));
        }

        // The list a category's Show more button belongs to: the closest earlier sibling of the button's row that holds
        // courses, so a notice between them is skipped. The search stops at another category's button, so a category
        // never takes a neighbour's list. The shortcode's limit marker names the category of its list, and a list named
        // for another category is never taken, which keeps a button whose own list is missing from taking the list
        // before it when the template has no category wrapper. A marker without a category, from older output or for
        // a slug that is not slug-shaped, matches by position. Empty when the button has no list.
        listOf(button) {
            const slug = $(button).attr('data-slug') || '';
            let $list = $();
            $(button).parent().prevAll().each((i, sibling) => {
                const $sibling = $(sibling);
                if ($sibling.find('.arlo-btn-more-event').addBack('.arlo-btn-more-event').length > 0) return false;
                const named = $sibling.find('.arlo-events-limit').first().attr('data-slug');
                if (named !== undefined && named !== slug) return false;
                if ($sibling.find(this.itemSelector + ', .arlo-events-limit').length > 0) {
                    $list = $sibling;
                    return false;
                }
            });
            return $list;
        }

        // A button's own row is hidden with it. A parent that holds anything besides this button, another Show more button
        // or text included, is not a row of its own, so only the button is hidden and the other content stays.
        hideButton(button) {
            const element = $(button)[0];
            const $row = $(element).parent();
            const holdsOther = $row.contents().filter((i, node) => (
                node.nodeType === 1 ? node !== element : node.nodeType === 3 && node.nodeValue.trim() !== ''
            )).length > 0;
            (holdsOther ? $(element) : $row).hide();
        }

        // Every list is fetched with one course more than the template limit, which only signals that another page exists.
        // The shortcode marks the end of each list with that limit; a list without the marker uses 3.
        getLimit($list) {
            const limit = parseInt($list.find('.arlo-events-limit').first().data('limit'), 10);
            return limit > 0 ? limit : 3;
        }

        // Drops a category's look-ahead course, or hides its button when there is nothing more.
        initCategoryButton(button) {
            const $list = this.listOf(button);
            // A button without a list has nothing to show more of.
            if ($list.length === 0) {
                this.hideButton(button);
                return;
            }
            const more = $list.find(this.itemSelector).length > this.getLimit($list);
            if (more) {
                $list.find(this.itemSelector + ':last').remove();
            } else {
                this.hideButton(button);
            }
            $list.find(this.itemSelector).removeClass('hide');
        }

        // The next batch is requested from the page the visitor is on, so the page's own template, shortcode attributes,
        // region and filters apply as they did to the first batch. Only the paging and the clicked category are added;
        // the server ignores the address's own page number for these requests.
        batchUrl(categorySlug, page) {
            const url = new URL(window.location.href);
            url.hash = '';
            url.searchParams.set('pagefor', 'event');
            url.searchParams.set('epage', page);
            if (categorySlug) {
                // The button's own category is requested even when the page has no category filter.
                url.searchParams.set('arlo-category', categorySlug);
            }
            return url.toString();
        }

        // A response lists every group the request selects, and a category with descendants selects each of them, so only
        // the courses of the group whose button has the clicked slug are taken. A group the response does not list has no more.
        groupCourses(html, slug) {
            const $groupList = $(html).find('.arlo-btn-more-event').filter(function() {
                return ($(this).attr('data-slug') || '') === slug;
            }).first();
            return this.listOf($groupList).find(this.itemSelector);
        }

        // Delegated, so the buttons of categories that Show more categories appends work too.
        bindShowMoreCourses() {
            $('body').on('click', '.arlo-btn-more-event', (event) => {
                const $button = $(event.currentTarget);
                if ($button.data('loading') || this.listOf($button).length === 0) return;
                $button.data('loading', true);
                $button.attr('disabled', 'disabled');
                const page = (parseInt($button.data('page'), 10) || 1) + 1;
                // Read as written, not through .data(), which would turn a numeric-looking value into a number.
                const slug = $button.attr('data-slug') || '';
                $.get(this.batchUrl(slug, page), (html) => {
                    const $courses = this.groupCourses(html, slug);
                    const $list = this.listOf($button);
                    const limit = this.getLimit($list);
                    $courses.slice(0, limit).removeClass('hide').appendTo($list);
                    if ($courses.length <= limit) {
                        this.hideButton($button); // no look-ahead course: nothing more
                    }
                    $button.data('page', page);
                }).always(() => {
                    // A failed request keeps the courses and the page number, so the visitor can retry the same page.
                    $button.data('loading', false);
                    $button.removeAttr('disabled');
                });
            });
        }

        // The next page of categories is appended to the category wrapper like the items of any paged list; each
        // appended category then gets the same set-up as the ones on the page.
        bindShowMoreCategories() {
            new PagedList({
                ...PAGED_LISTS.categories,
                onAppend: ($categories) => {
                    $categories.find('.arlo-btn-more-event').each((i, button) => this.initCategoryButton(button));
                },
            }).start();
        }
    }

    // The event page lists its sessions with the first batch shown and the rest held back until Show more.
    function startEventSessions() {
        arloInitDiscountIcon();
        let batch = 1;
        // [arlo_event_list_item show="n"] emits an .arlo-show-more-hidden list after the first n items, so the size
        // of the first batch is the size of every batch.
        const $boundary = $('.arlo-event-list-items .arlo-show-more-hidden').first();
        const batchSize = Math.max($boundary.length
            ? $boundary.prevAll('.arlo-event-list-items-item').length
            : $('.arlo-event-list-items-item').length, 1);

        function showBatches() {
            let index = 0;
            let visible = 0;
            $('.arlo-event-list-items-item').each(function() {
                if (index < batch * batchSize) {
                    $(this).show();
                    visible += 1;
                } else {
                    $(this).hide();
                }
                index += 1;
            });
            if (visible >= $('.arlo-event-list-items-item').length) {
                $('.arlo-event-more').hide();
            }
        }

        $('.arlo-event-more').click(function() {
            batch += 1;
            showBatches();
        });
        showBatches();
    }

    // The session popups of the rows an upcoming Show more appends. The rows already on the page are set up by
    // public/assets/js/public.js.
    const sessionModals = {
        // The shortcode emits the popup content beside its trigger, so only an element with the target's id inside the
        // trigger's parent counts. A missing, malformed, empty or repeated id elsewhere on the page never fills a modal.
        // Returns '' when the trigger has no content of its own. public/assets/js/public.js repeats this lookup for the
        // rows already on the page, because it cannot depend on a theme script; keep the two in step.
        contentOf(trigger) {
            const target = String($(trigger).attr('data-target') || '').trim();
            if (target.length < 2 || target.charAt(0) !== '#') return '';
            const $content = $(trigger).parent().find('[id]').filter((i, el) => el.id === target.slice(1));
            return $content.length === 1 ? $content.html().trim() : '';
        },

        attach($rows) {
            const contentOf = this.contentOf;
            $rows.find('.arlo-sessions-popup-trigger').each(function(i, el) {
                const content = contentOf(el);
                if (!content) {
                    // The trigger's empty link would reload the page.
                    $(el).click((e) => e.preventDefault());
                    return;
                }
                const modal = new tingle.modal({
                    footer: true,
                    cssClass: ['arlo'],
                    closeMethods: ['overlay', 'button', 'escape']
                });

                modal.setContent(content);

                modal.addFooterBtn('Close', 'arlo-close-btn button btn btn-primary', function() {
                    modal.close();
                });

                $(el).click(function(e) {
                    e.preventDefault();
                    modal.open();
                });
            });
            arloAppenCloseIconToModal();
        },
    };

    function pagedListComponent(selector, listConfig) {
        return { selector, start: () => new PagedList(listConfig).start() };
    }

    // Each component starts when the markup it works on is on the page, so a template that leaves one out does not
    // stop the others.
    const components = [
        {
            selector: GROUPED_LIST_KINDS.map((kind) => kind.pageSelector).join(', '),
            start: () => GroupedList.detect().start(),
        },
        pagedListComponent('.arlo-event-search', PAGED_LISTS.search),
        { selector: '.arlo-event-page', start: startEventSessions },
        pagedListComponent('.arlo-presenters', PAGED_LISTS.presenters),
        pagedListComponent('.arlo-upcoming', {
            ...PAGED_LISTS.upcoming,
            onAppend: ($rows) => sessionModals.attach($rows),
        }),
        pagedListComponent('.arlo-venue-list', PAGED_LISTS.venues),
        pagedListComponent('.arlo-activities', PAGED_LISTS.activities),
    ];
    components.forEach((component) => {
        if ($(component.selector).length > 0) component.start();
    });
});
