/**
 * NOBS — Staff → Arena: creador de salas a medida.
 *
 * Pinta una fila por hueco del mapa elegido (humano / IA / cerrado, IA, dificultad, facción,
 * equipo) y, al enviar, empaqueta todo en el campo oculto «plan» (JSON). El agente de salas
 * valida el plan contra el catálogo del juego; esto solo ayuda a rellenarlo.
 */
(function () {
    'use strict';

    document.querySelectorAll('form[data-arena-medida]').forEach(function (form) {
        var cat;
        try {
            cat = JSON.parse(form.querySelector('script[data-arena-catalogo]').textContent);
        } catch (e) {
            return;
        }
        var selMapa = form.querySelector('[data-arena-mapa]');
        var cuerpo = form.querySelector('[data-arena-huecos]');
        var campoPlan = form.querySelector('[data-arena-plan]');
        var chkGuardar = form.querySelector('[data-arena-guardar]');
        var inputSlug = form.querySelector('[data-arena-slug]');

        function opcion(valor, texto, elegida) {
            var o = document.createElement('option');
            o.value = valor;
            o.textContent = texto;
            if (elegida) { o.selected = true; }
            return o;
        }

        function select(clase, opciones) {
            var s = document.createElement('select');
            s.className = 'form__select ' + clase;
            opciones.forEach(function (o) { s.appendChild(o); });
            return s;
        }

        function fila(i) {
            var tr = document.createElement('tr');
            var tipo = select('h-tipo', [opcion('humano', 'Humano', true), opcion('ia', 'IA'), opcion('cerrado', 'Cerrado')]);
            var ia = select('h-ia', cat.ias.map(function (a, n) { return opcion(a.js, a.nombre, n === 0); }));
            var dif = select('h-dif', cat.dificultades.map(function (d) { return opcion(d, { Easy: 'Fácil', Medium: 'Media', Hard: 'Difícil', Insane: 'Locura' }[d] || d, d === 'Medium'); }));
            var fac = select('h-fac', cat.facciones.map(function (f, n) { return opcion(f, f, n === 0); }));
            var equipo = document.createElement('input');
            equipo.type = 'number';
            equipo.min = '0';
            equipo.max = '9';
            equipo.value = String(i);
            equipo.className = 'form__text h-equipo';
            var celdas = [document.createTextNode(String(i + 1)), tipo, ia, dif, fac, equipo];
            celdas.forEach(function (c) {
                var td = document.createElement('td');
                td.appendChild(c);
                tr.appendChild(td);
            });
            function ajustar() {
                var esIa = tipo.value === 'ia';
                ia.disabled = dif.disabled = fac.disabled = !esIa;
                equipo.disabled = tipo.value === 'cerrado';
            }
            tipo.addEventListener('change', ajustar);
            ajustar();
            return tr;
        }

        function pintar() {
            var opt = selMapa.options[selMapa.selectedIndex];
            var n = opt ? parseInt(opt.getAttribute('data-jugadores'), 10) : 0;
            cuerpo.textContent = '';
            for (var i = 0; i < n; i++) { cuerpo.appendChild(fila(i)); }
        }

        selMapa.addEventListener('change', pintar);
        pintar();

        if (chkGuardar && inputSlug) {
            chkGuardar.addEventListener('change', function () {
                inputSlug.disabled = !chkGuardar.checked;
                inputSlug.required = chkGuardar.checked;
            });
        }

        form.addEventListener('submit', function (ev) {
            var valor = function (campo) { return form.querySelector('[data-campo="' + campo + '"]'); };
            var huecos = Array.prototype.map.call(cuerpo.querySelectorAll('tr'), function (tr) {
                return {
                    tipo: tr.querySelector('.h-tipo').value,
                    ia: tr.querySelector('.h-ia').value,
                    dificultad: tr.querySelector('.h-dif').value,
                    faccion: tr.querySelector('.h-fac').value,
                    equipo: parseInt(tr.querySelector('.h-equipo').value || '0', 10)
                };
            });
            if (!huecos.some(function (h) { return h.tipo === 'humano'; })) {
                ev.preventDefault();
                window.alert('Hace falta al menos un hueco para humanos.');
                return;
            }
            campoPlan.value = JSON.stringify({
                nombre: valor('nombre').value,
                mapa: valor('mapa').value,
                huecos: huecos,
                alianzas: parseInt(valor('alianzas').value, 10),
                energia: parseInt(valor('energia').value, 10),
                bases: parseInt(valor('bases').value, 10),
                tecnologia: parseInt(valor('tecnologia').value, 10),
                chatarreros: parseInt(valor('chatarreros').value, 10),
                espectadores: parseInt(valor('espectadores').value || '0', 10),
                contrasena: valor('contrasena').value,
                bloquear: valor('bloquear').checked
            });
        });
    });
})();
