/**
 * PsiClinic - hoja de estilos / comportamiento de la interfaz.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

(() => {
    const csrfToken = () => document.querySelector('input[name="_token"]')?.value ?? '';

    const initTheme = () => {
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.addEventListener('click', async () => {
                const root = document.documentElement;
                const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
                root.dataset.theme = next;

                const body = new URLSearchParams({ theme: next, _token: csrfToken() });
                await fetch('/tema', { method: 'POST', body }).catch(() => {});
            });
        });
    };

    const initSidebar = () => {
        const sidebar = document.querySelector('[data-sidebar]');
        document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
            sidebar?.classList.toggle('is-open');
        });
    };

    const initPasswordToggles = () => {
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });
    };

    const initDemoFill = () => {
        document.querySelectorAll('[data-fill-user]').forEach((button) => {
            button.addEventListener('click', () => {
                const identifier = document.getElementById('identifier');
                const password = document.getElementById('password');
                if (identifier) identifier.value = button.dataset.fillUser;
                if (password) password.value = button.dataset.fillPass;
                identifier?.focus();
            });
        });
    };

    const initGlobalSearch = () => {
        const input = document.querySelector('[data-global-search]');
        const panel = document.querySelector('[data-search-results]');
        if (!input || !panel) return;

        let timer = null;

        const render = (results) => {
            if (results.length === 0) {
                panel.innerHTML = '<div class="search-results__group">Sin coincidencias</div>';
                panel.classList.add('is-open');
                return;
            }

            let currentGroup = '';
            panel.innerHTML = results.map((item) => {
                const header = item.group === currentGroup
                    ? ''
                    : `<div class="search-results__group">${item.group}</div>`;
                currentGroup = item.group;
                return `${header}<a class="search-results__item" href="${item.url}">
                    <span>${item.label}</span><span class="text-xs text-muted">${item.meta}</span></a>`;
            }).join('');
            panel.classList.add('is-open');
        };

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const term = input.value.trim();

            if (term.length < 2) {
                panel.classList.remove('is-open');
                return;
            }

            timer = setTimeout(async () => {
                try {
                    const response = await fetch(`/buscar?q=${encodeURIComponent(term)}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await response.json();
                    render(data.results ?? []);
                } catch {
                    panel.classList.remove('is-open');
                }
            }, 220);
        });

        document.addEventListener('click', (event) => {
            if (!panel.contains(event.target) && event.target !== input) {
                panel.classList.remove('is-open');
            }
        });

        document.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                input.focus();
                input.select();
            }
            if (event.key === 'Escape') panel.classList.remove('is-open');
        });
    };

    const initTabs = () => {
        document.querySelectorAll('[data-tabs]').forEach((container) => {
            const tabs = container.querySelectorAll('.tab');
            const panels = document.querySelectorAll(`[data-tab-panel][data-tab-group="${container.dataset.tabs}"]`);

            const activate = (name) => {
                tabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.tab === name));
                panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.tabPanel === name));
                if (history.replaceState) history.replaceState(null, '', `#${name}`);
            };

            tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.tab)));

            const fromHash = window.location.hash.replace('#', '');
            if (fromHash && [...tabs].some((tab) => tab.dataset.tab === fromHash)) activate(fromHash);
        });
    };

    const initFlash = () => {
        document.querySelectorAll('[data-flash-stack] .flash').forEach((flash, index) => {
            setTimeout(() => {
                flash.style.transition = 'opacity 300ms, transform 300ms';
                flash.style.opacity = '0';
                flash.style.transform = 'translateX(16px)';
                setTimeout(() => flash.remove(), 320);
            }, 4800 + index * 350);
        });
    };

    const initConfirm = () => {
        document.querySelectorAll('[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm(form.dataset.confirm)) event.preventDefault();
            });
        });
    };

    const initLikert = () => {
        const form = document.querySelector('[data-likert-form]');
        if (!form) return;

        const items = form.querySelectorAll('.likert__item');
        const bar = form.querySelector('[data-likert-progress]');
        const counter = form.querySelector('[data-likert-counter]');

        const refresh = () => {
            let answered = 0;
            items.forEach((item) => {
                const isAnswered = item.querySelector('input:checked') !== null;
                item.classList.toggle('is-answered', isAnswered);
                if (isAnswered) answered += 1;
            });

            if (bar) bar.style.width = `${(answered / items.length) * 100}%`;
            if (counter) counter.textContent = `${answered} de ${items.length}`;
        };

        form.addEventListener('change', refresh);
        refresh();
    };

    const initInvoiceRows = () => {
        const table = document.querySelector('[data-invoice-items]');
        if (!table) return;

        const recalc = () => {
            let subtotal = 0;
            table.querySelectorAll('tbody tr').forEach((row) => {
                const quantity = parseFloat(row.querySelector('[name="quantity[]"]')?.value ?? '0') || 0;
                const price = parseFloat(row.querySelector('[name="unit_price[]"]')?.value ?? '0') || 0;
                const amount = quantity * price;
                subtotal += amount;
                const cell = row.querySelector('[data-amount]');
                if (cell) cell.textContent = amount.toLocaleString('es-CO', { minimumFractionDigits: 2 });
            });

            const rate = parseFloat(document.querySelector('[name="tax_rate"]')?.value ?? '0') || 0;
            const tax = subtotal * (rate / 100);

            const set = (selector, value) => {
                const node = document.querySelector(selector);
                if (node) node.textContent = value.toLocaleString('es-CO', { minimumFractionDigits: 2 });
            };

            set('[data-subtotal]', subtotal);
            set('[data-tax]', tax);
            set('[data-total]', subtotal + tax);
        };

        document.querySelector('[data-add-row]')?.addEventListener('click', () => {
            const body = table.querySelector('tbody');
            const template = body.querySelector('tr');
            const clone = template.cloneNode(true);
            clone.querySelectorAll('input').forEach((input) => {
                input.value = input.name === 'quantity[]' ? '1' : '';
            });
            body.appendChild(clone);
            recalc();
        });

        table.addEventListener('input', recalc);
        table.addEventListener('click', (event) => {
            if (!event.target.closest('[data-remove-row]')) return;
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length > 1) event.target.closest('tr').remove();
            recalc();
        });

        document.querySelector('[name="tax_rate"]')?.addEventListener('input', recalc);
        recalc();
    };

    const initSignaturePad = () => {
        const canvas = document.querySelector('[data-signature]');
        if (!canvas) return;

        const target = document.querySelector('[name="signature_svg"]');
        const points = [];
        let drawing = false;
        let current = [];

        const position = (event) => {
            const rect = canvas.getBoundingClientRect();
            const source = event.touches ? event.touches[0] : event;
            return [
                Math.round(((source.clientX - rect.left) / rect.width) * 600),
                Math.round(((source.clientY - rect.top) / rect.height) * 200),
            ];
        };

        const redraw = () => {
            const paths = points
                .filter((stroke) => stroke.length > 1)
                .map((stroke) => `<path d="M${stroke.map((p) => p.join(' ')).join(' L')}" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>`)
                .join('');

            canvas.innerHTML = paths;
            if (target) {
                target.value = paths === ''
                    ? ''
                    : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 200">${paths}</svg>`;
            }
        };

        const start = (event) => {
            drawing = true;
            current = [position(event)];
            points.push(current);
        };

        const move = (event) => {
            if (!drawing) return;
            event.preventDefault();
            current.push(position(event));
            redraw();
        };

        const stop = () => { drawing = false; };

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', stop);
        canvas.addEventListener('touchstart', start, { passive: true });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', stop);

        document.querySelector('[data-signature-clear]')?.addEventListener('click', () => {
            points.length = 0;
            redraw();
        });
    };

    const initPatientPicker = () => {
        document.querySelectorAll('[data-reload-on-change]').forEach((select) => {
            select.addEventListener('change', () => {
                const url = new URL(window.location.href);
                url.searchParams.set(select.dataset.reloadOnChange, select.value);
                window.location.href = url.toString();
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initSidebar();
        initPasswordToggles();
        initDemoFill();
        initGlobalSearch();
        initTabs();
        initFlash();
        initConfirm();
        initLikert();
        initInvoiceRows();
        initSignaturePad();
        initPatientPicker();
    });
})();
