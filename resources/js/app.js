import { createDailyLogDraft } from './daily-log-draft.js';

// Cockpit interaction primitives. Livewire owns form submission and navigation.
let disposePage = () => {};
let notificationTimer;
const visible = element => element.isConnected && element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
const notify = message => {
    const region = document.getElementById('ui-notifications');
    if (!region) return;
    region.textContent = String(message);
    clearTimeout(notificationTimer);
    notificationTimer = setTimeout(() => { region.textContent = ''; }, 6000);
};

function initializePage() {
    disposePage();
    const controller = new AbortController();
    const { signal } = controller;
    const banner = document.querySelector('[data-network-banner]');
    const network = () => {
        if (banner) banner.hidden = navigator.onLine;
        document.querySelectorAll('[data-online-submit]').forEach(button => { button.disabled = !navigator.onLine; });
        document.querySelectorAll('[data-connection-status]').forEach(element => { const text = navigator.onLine ? 'Zum Übertragen „Eintrag speichern“ wählen.' : 'Offline · Entwurf bleibt auf diesem Gerät.'; if (element.textContent !== text) element.textContent = text; });
    };
    window.addEventListener('online', network, { signal });
    window.addEventListener('offline', network, { signal });
    window.addEventListener('notify', event => {
        const detail = event.detail;
        notify(typeof detail === 'string' ? detail : detail?.message ?? detail?.[0] ?? 'Änderung gespeichert.');
    }, { signal });

    window.addEventListener('daily-log-saved', event => {
        const userId = document.body.dataset.userId;
        try { localStorage.removeItem(`bt:daily-log-draft:v1:${userId}:${event.detail.projectId}`); } catch {}
        notify('Tagesbericht auf Server gespeichert.');
    }, { signal });

    const activeDialogs = new Map();
    let inertElements = [];
    let lastTop = null;
    const focusables = dialog => [...dialog.querySelectorAll('a[href], button, input:not([type="hidden"]), textarea, select, summary, [tabindex="0"]')].filter(element => visible(element) && !element.disabled && !element.closest('[inert]'));
    const closer = dialog => dialog.querySelector('[data-dialog-close]') || [...dialog.querySelectorAll('button')].find(button => /schließen|abbrechen|^✕$|^×$|^ESC$/i.test([button.textContent.trim(), button.title, button.getAttribute('aria-label') || ''].join(' ').trim())) || [...dialog.querySelectorAll('button')].find(button => /\$set\([^,]+,\s*false\)/.test(button.getAttribute('wire:click') || ''));
    const refresh = () => {
        const dialogs = [...document.querySelectorAll('[data-ui-dialog]')].filter(visible).filter(dialog => !dialog.parentElement.closest('[data-ui-dialog]'));
        const closed = [...activeDialogs.keys()].filter(dialog => !dialogs.includes(dialog));
        let restore;
        for (const dialog of closed) { restore = activeDialogs.get(dialog); activeDialogs.delete(dialog); }
        inertElements.forEach(element => { element.inert = false; });
        inertElements = [];
        const top = dialogs.sort((a, b) => Number(getComputedStyle(a).zIndex) - Number(getComputedStyle(b).zIndex)).at(-1);
        for (const dialog of dialogs) {
            if (!activeDialogs.has(dialog)) {
                activeDialogs.set(dialog, document.activeElement);
                dialog.tabIndex = -1;
                dialog.setAttribute('role', 'dialog');
                dialog.setAttribute('aria-modal', 'true');
                const heading = dialog.querySelector('h1,h2,h3,h4');
                if (!dialog.hasAttribute('aria-label') && !dialog.hasAttribute('aria-labelledby')) dialog.setAttribute('aria-label', heading?.textContent.trim() || 'Dialog');
                const button = closer(dialog);
                if (button && !button.getAttribute('aria-label') && /^[✕×]$/.test(button.textContent.trim())) button.setAttribute('aria-label', 'Dialog schließen');
            }
        }
        if (top) {
            let branch = top;
            while (branch.parentElement && branch.parentElement !== document.documentElement) {
                for (const sibling of branch.parentElement.children) {
                    if (sibling !== branch && !sibling.inert && !['SCRIPT', 'STYLE', 'LINK'].includes(sibling.tagName)) { sibling.inert = true; inertElements.push(sibling); }
                }
                branch = branch.parentElement;
                if (branch === document.body) break;
            }
            document.body.classList.add('ui-dialog-body-locked');
            if (lastTop !== top) (focusables(top)[0] || top).focus({ preventScroll: true });
        } else {
            document.body.classList.remove('ui-dialog-body-locked');
            if (restore && visible(restore)) restore.focus({ preventScroll: true });
        }
        lastTop = top;
        network();
    };
    document.addEventListener('keydown', event => {
        const top = lastTop;
        if (!top || !visible(top)) return;
        if (event.key === 'Escape') {
            const button = closer(top);
            if (button) { event.preventDefault(); event.stopImmediatePropagation(); button.click(); }
        }
        if (event.key === 'Tab') {
            const elements = focusables(top);
            if (!elements.length) { event.preventDefault(); top.focus(); return; }
            const first = elements[0], last = elements.at(-1);
            if (event.shiftKey && (document.activeElement === first || !top.contains(document.activeElement))) { event.preventDefault(); last.focus(); }
            if (!event.shiftKey && (document.activeElement === last || !top.contains(document.activeElement))) { event.preventDefault(); first.focus(); }
        }
    }, { capture: true, signal });
    // Handwritten form labels receive explicit associations after Livewire morphs.
    const labelFields = () => {
        document.querySelectorAll('.ui-app label:not([for])').forEach(label => {
            if (label.querySelector('input,select,textarea')) return;
            const field = label.parentElement.querySelector('input:not([type="hidden"]),select,textarea');
            if (!field) return;
            if (!field.id) field.id = 'ui-field-' + crypto.randomUUID();
            label.htmlFor = field.id;
        });
    };
    let scheduled = false;
    const observer = new MutationObserver(() => {
        if (scheduled) return;
        scheduled = true;
        queueMicrotask(() => { scheduled = false; labelFields(); refresh(); });
    });
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
    labelFields(); refresh();
    disposePage = () => {
        controller.abort(); observer.disconnect(); inertElements.forEach(element => { element.inert = false; });
        document.body.classList.remove('ui-dialog-body-locked');
    };
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
}

window.dailyLogDraft = createDailyLogDraft;

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status }) => {
            if (status === 419) return;
            notify('Übertragung fehlgeschlagen. Bitte erneut versuchen; lokale Tagesbericht-Entwürfe bleiben erhalten.');
            document.querySelectorAll('[data-submit-status]').forEach(element => { element.textContent = 'Speichern fehlgeschlagen · bitte erneut versuchen.'; });
        });
    });
});
document.addEventListener('livewire:navigated', initializePage);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializePage, { once: true }); else initializePage();
