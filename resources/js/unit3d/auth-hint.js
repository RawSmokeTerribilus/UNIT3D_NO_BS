/**
 * Login form: nudge users who type their email into the username field.
 *
 * A valid username is `alpha_dash` (see CreateNewUser), so it can never contain
 * an "@". Everything below is gated on that character and cannot fire for
 * someone typing a legitimate username.
 *
 * Hovering the submit button sends the whole card fleeing to one edge of the
 * viewport and then the other. The hint appears on the second dodge and the
 * card settles back in the centre on the third, so the button is always
 * reachable. Pointer-only: touch and keyboard never trigger it, and those users
 * get the server-side message on submit instead.
 */
document.addEventListener('DOMContentLoaded', () => {
    const card = document.querySelector('.auth-form');
    const username = document.getElementById('username');
    const button = document.querySelector('.auth-form__primary-button');

    if (!card || !username || !button) {
        return;
    }

    const HINT_AFTER = 2;
    const SURRENDER_AFTER = 3;
    const GUTTER = 10;
    const TRAVEL_MS = 350;

    const hint = document.createElement('span');
    hint.className = 'auth-form__hint';
    hint.hidden = true;
    hint.textContent = username.dataset.emailHint ?? '';
    username.insertAdjacentElement('afterend', hint);

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let dodges = 0;

    const looksLikeEmail = () => username.value.includes('@');

    // The card is margin-auto centred, so the free space is the same on both
    // sides. offsetWidth is a layout value and is not affected by the transform
    // already applied, so this stays correct dodge after dodge. Clamping at 0
    // means a narrow window simply does not move the card instead of dragging a
    // horizontal scrollbar into existence.
    const room = () => Math.max(0, (window.innerWidth - card.offsetWidth) / 2 - GUTTER);

    const settle = () => {
        card.style.transform = '';
    };

    const reset = () => {
        dodges = 0;
        hint.hidden = true;
        card.style.transition = '';
        settle();
    };

    username.addEventListener('input', () => {
        if (!looksLikeEmail()) {
            reset();
        }
    });

    button.addEventListener('mouseover', () => {
        if (!looksLikeEmail() || reduceMotion || dodges >= SURRENDER_AFTER) {
            return;
        }

        dodges++;

        if (dodges >= HINT_AFTER) {
            hint.hidden = false;
        }

        card.style.transition = `transform ${TRAVEL_MS}ms ease-out`;

        if (dodges >= SURRENDER_AFTER) {
            settle();

            return;
        }

        // Odd dodge flees left, even dodge flees right.
        const direction = dodges % 2 === 1 ? -1 : 1;
        card.style.transform = `translateX(${direction * room()}px)`;
    });

    window.addEventListener('resize', () => {
        if (dodges > 0 && dodges < SURRENDER_AFTER) {
            const direction = dodges % 2 === 1 ? -1 : 1;
            card.style.transform = `translateX(${direction * room()}px)`;
        }
    });
});
