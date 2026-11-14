// Passive, opt-in observation in a separate browser. Never records form values,
// URLs, object IDs, cookies or DOM contents; never submits or clicks anything.
(() => {
    const key = 'f019-pending-click';
    let pending = JSON.parse(sessionStorage.getItem(key) || 'null');
    const operation = value => {
        const url = new URL(value, location.href);
        if (url.origin !== location.origin || (url.pathname !== '/' && url.pathname !== '/index.php')) return null;
        const controller = url.searchParams.get('controller');
        const action = url.searchParams.get('action') || 'show';
        return controller && /^[A-Za-z0-9_]+$/.test(controller) && /^[A-Za-z0-9_]+$/.test(action) ? controller + '/' + action : null;
    };
    const start = (url, kind) => {
        const op = operation(url);
        if (!op || /^(Auth|RvAccess)Controller\//.test(op)) return;
        pending = {operation: op, interaction: kind, start: performance.timeOrigin + performance.now()};
        sessionStorage.setItem(key, JSON.stringify(pending));
    };
    const finish = async (successful = true) => {
        if (!pending) return;
        const result = pending;
        await document.fonts.ready;
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        if (pending !== result) return;
        pending = null;
        sessionStorage.removeItem(key);
        const visibleError = Array.from(document.querySelectorAll('.form-errors,.alert-error')).some(e => e.getClientRects().length);
        window.f019Record({operation: result.operation, interaction: result.interaction,
            ms: performance.timeOrigin + performance.now() - result.start, success: successful && !visibleError}).catch(() => {});
    };
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (link && !link.getAttribute('href').startsWith('#')) {
            start(link.href, link.className.includes('js-modal') ? 'form_open' : 'navigation');
        }
        const button = event.target.closest('button,input[type=submit]');
        if (button && button.type === 'submit' && button.form && !button.disabled) start(button.form.action, 'save');
    }, true);
    document.addEventListener('submit', event => {
        if (!pending) start(event.target.action, 'save');
    }, true);
    window.addEventListener('pageshow', () => finish());
    const open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url, ...args) {
        const op = operation(url);
        this.addEventListener('loadend', () => {
            if (pending && pending.operation === op && this.status >= 200 && this.status < 300 &&
                !this.getResponseHeader('X-Ajax-Redirect') && !this.getResponseHeader('Location')) finish();
            if (pending && pending.operation === op && this.status >= 400) finish(false);
        }, {once: true});
        return open.call(this, method, url, ...args);
    };
})();
