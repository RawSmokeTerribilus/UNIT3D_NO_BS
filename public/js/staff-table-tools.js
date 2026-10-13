/*
 * NOBS — Nuclear Order Bit Syndicate
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 *
 * Orden y filtro por columna para las tablas de los botones de staff
 * (<table class="data-table" data-tools>).
 *
 * - Pulsar una cabecera ordena por esa columna; otra vez, al revés. Las celdas
 *   pueden llevar data-sort con el valor real (número o fecha ISO) cuando el
 *   texto visible no ordena bien («nunca», «3,6 GiB», «13/10/2026»).
 * - Filtro: se elige la columna (o todas) y se escribe. Texto = «contiene», sin
 *   distinguir mayúsculas ni acentos. Con >, <, >=, <= o = delante de un número,
 *   compara números.
 * - Seguridad: una fila oculta por el filtro queda con su casilla DESACTIVADA,
 *   así que no se envía. Lo que se ve es lo que se manda.
 */
(function () {
    'use strict';

    function norm(s) {
        return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }

    function cellValue(td) {
        var v = td.getAttribute('data-sort');

        return v !== null ? v : td.textContent.replace(/\s+/g, ' ').trim();
    }

    function asNumber(v) {
        if (v === '' || v === null || v === undefined) {
            return NaN;
        }

        return Number(String(v).replace(',', '.'));
    }

    function compare(a, b) {
        var na = asNumber(a);
        var nb = asNumber(b);

        if (!isNaN(na) && !isNaN(nb)) {
            return na - nb;
        }

        return norm(a).localeCompare(norm(b), 'es');
    }

    function setup(table) {
        var thead = table.tHead;
        var tbody = table.tBodies[0];

        if (!thead || !tbody) {
            return;
        }

        var headers = Array.prototype.slice.call(thead.rows[0].cells);
        var hasChecks = tbody.querySelector('input[type="checkbox"]') !== null;

        function rows() {
            return Array.prototype.slice.call(tbody.rows);
        }

        // Barra de filtro
        var bar = document.createElement('div');
        bar.className = 'tt-bar';

        var select = document.createElement('select');
        select.className = 'form__select tt-col';
        select.appendChild(new Option('Todas las columnas', '-1'));
        headers.forEach(function (th, i) {
            var name = th.textContent.trim();

            if (name !== '') {
                select.appendChild(new Option(name, String(i)));
            }
        });

        var input = document.createElement('input');
        input.type = 'search';
        input.className = 'form__text tt-q';
        input.placeholder = 'Filtrar… (texto, o >5, <=10, =0)';

        var count = document.createElement('span');
        count.className = 'tt-count';

        bar.appendChild(select);
        bar.appendChild(input);

        function refreshCount() {
            var all = rows();
            var visible = all.filter(function (tr) { return tr.style.display !== 'none'; });
            var text = visible.length + ' de ' + all.length + ' filas';

            if (hasChecks) {
                var marked = visible.filter(function (tr) {
                    var cb = tr.querySelector('input[type="checkbox"]');

                    return cb && cb.checked;
                }).length;
                text += ' · ' + marked + ' marcadas (solo se envían estas)';
            }

            count.textContent = text;
        }

        if (hasChecks) {
            ['Marcar visibles', 'Desmarcar visibles'].forEach(function (label, i) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'form__button form__button--text';
                b.textContent = label;
                b.addEventListener('click', function () {
                    rows().forEach(function (tr) {
                        var cb = tr.querySelector('input[type="checkbox"]');

                        if (cb && !cb.disabled) {
                            cb.checked = i === 0;
                        }
                    });
                    refreshCount();
                });
                bar.appendChild(b);
            });
            tbody.addEventListener('change', refreshCount);
        }

        bar.appendChild(count);
        table.parentNode.insertBefore(bar, table);

        function matches(tr, col, q) {
            var cells = col < 0 ? Array.prototype.slice.call(tr.cells) : [tr.cells[col]];
            var m = q.match(/^(>=|<=|>|<|=)\s*(-?[\d.,]+)$/);

            return cells.some(function (td) {
                if (!td) {
                    return false;
                }

                if (m) {
                    var n = asNumber(cellValue(td));
                    var t = asNumber(m[2]);

                    if (isNaN(n)) {
                        return false;
                    }

                    switch (m[1]) {
                        case '>': return n > t;
                        case '<': return n < t;
                        case '>=': return n >= t;
                        case '<=': return n <= t;
                        default: return n === t;
                    }
                }

                return norm(td.textContent).indexOf(norm(q)) !== -1;
            });
        }

        function applyFilter() {
            var col = parseInt(select.value, 10);
            var q = input.value.trim();

            rows().forEach(function (tr) {
                var show = q === '' || matches(tr, col, q);
                var cb = tr.querySelector('input[type="checkbox"]');

                tr.style.display = show ? '' : 'none';

                if (cb) {
                    cb.disabled = !show;
                }
            });
            refreshCount();
        }

        select.addEventListener('change', applyFilter);
        input.addEventListener('input', applyFilter);

        // Orden
        headers.forEach(function (th, i) {
            if (th.textContent.trim() === '') {
                return;
            }

            th.classList.add('tt-sortable');
            th.title = 'Ordenar por esta columna';
            th.addEventListener('click', function () {
                var dir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';

                headers.forEach(function (h) {
                    h.removeAttribute('data-dir');
                });
                th.setAttribute('data-dir', dir);

                rows()
                    .sort(function (a, b) {
                        var r = compare(cellValue(a.cells[i]), cellValue(b.cells[i]));

                        return dir === 'asc' ? r : -r;
                    })
                    .forEach(function (tr) {
                        tbody.appendChild(tr);
                    });
            });
        });

        refreshCount();
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('table[data-tools]'), setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
