/**
 * Upload form: say out loud why the browser refused to submit.
 *
 * When a `required` field is empty or invalid the browser blocks the submit
 * before anything leaves the device. Chrome shows a bubble on the field;
 * Firefox for Android shows nothing at all, so the button looks dead and
 * nothing reaches the server or the console. Several fields here turn
 * `required` on and off through Alpine (`x-bind:required`), and one of them
 * can end up required while hidden, where no browser can point at it.
 *
 * `invalid` does not bubble, so it is caught in the capture phase. The events
 * for every failing field fire back to back during the submit attempt; they are
 * collected and rendered once, right above the submit button.
 *
 * Staging carries an extra debug line (page load type, whether the file input
 * still holds a file); production leaves it out.
 */
const init = () => {
    const form = document.getElementById('upload-form');
    const submit = document.getElementById('post');

    if (!form || !submit) {
        return;
    }

    const notice = document.createElement('div');
    notice.setAttribute('role', 'alert');
    notice.hidden = true;
    Object.assign(notice.style, {
        margin: '0 0 12px',
        padding: '10px 12px',
        border: '1px solid #e5484d',
        borderRadius: '6px',
        background: 'rgba(229, 72, 77, 0.12)',
        color: 'inherit',
        lineHeight: '1.4',
    });
    submit.closest('.form__group').insertAdjacentElement('beforebegin', notice);

    const text = {
        title: form.dataset.invalidTitle ?? 'Not sent. Check:',
        hidden: form.dataset.invalidHidden ?? 'hidden field',
    };

    let failing = [];
    let scheduled = false;

    const labelFor = (field) => {
        const label = field.id ? form.querySelector(`label[for="${CSS.escape(field.id)}"]`) : null;
        const name = label?.textContent.replace(/\s+/g, ' ').trim();

        return name || field.name || field.id || field.tagName.toLowerCase();
    };

    const isHidden = (field) => field.type === 'hidden' || field.getClientRects().length === 0;

    const render = () => {
        scheduled = false;

        if (failing.length === 0) {
            return;
        }

        notice.replaceChildren();

        const title = document.createElement('strong');
        title.textContent = text.title;
        notice.append(title);

        const list = document.createElement('ul');
        list.style.margin = '6px 0 0';
        list.style.paddingLeft = '18px';

        for (const field of failing) {
            const item = document.createElement('li');
            const parts = [labelFor(field)];

            if (field.validationMessage) {
                parts.push(field.validationMessage);
            }

            if (isHidden(field)) {
                parts.push(`(${text.hidden})`);
            }

            item.textContent = parts.join(' — ');
            list.append(item);
        }

        notice.append(list);

        notice.hidden = false;
        notice.scrollIntoView({ block: 'center', behavior: 'smooth' });

        failing = [];
    };

    form.addEventListener(
        'invalid',
        (event) => {
            failing.push(event.target);

            if (!scheduled) {
                scheduled = true;
                setTimeout(render, 0);
            }
        },
        true,
    );

    form.addEventListener('submit', () => {
        notice.hidden = true;
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
