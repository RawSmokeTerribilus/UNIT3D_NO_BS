/**
 * Touch menus: tap to open, tap again to close.
 *
 * The drawer dropdowns (mobile top bar) and the profile tab menus opened with
 * `:focus-within`. A second tap on the trigger keeps the focus, so the menu
 * never closed: you had to tap somewhere blank. Mobile browsers also leave
 * `:hover` stuck after a tap, and Safari does not focus a <button> on tap at
 * all, so focus could not be the switch.
 *
 * This keeps an explicit `is-open` class on the menu item instead, accordion
 * style (opening one closes its open siblings). `:focus-within` stays in the
 * CSS for keyboard users; closing here also drops the focus so it does not
 * reopen.
 *
 * The click is matched on the whole menu HEADER, not on the trigger element:
 * the focus that comes with the tap opens the menu and can shift the layout
 * between finger down and up, so the click often lands on the <li> itself. A
 * click inside the open submenu, or on any real link, is left alone.
 */
const ITEMS = '.top-nav.mobile .top-nav__dropdown, .nav-tab-menu';

document.addEventListener('click', (event) => {
    const item = event.target.closest(ITEMS);

    if (!item || event.target.closest('a[href]')) {
        return;
    }

    const list = event.target.closest('ul');

    if (list && list !== item.parentElement && item.contains(list)) {
        return;
    }

    const opening = !item.classList.contains('is-open');

    for (const sibling of item.parentElement.children) {
        if (sibling !== item) {
            sibling.classList.remove('is-open');
        }
    }

    item.classList.toggle('is-open', opening);

    if (!opening) {
        document.activeElement?.blur?.();
    }

    event.preventDefault();
});
